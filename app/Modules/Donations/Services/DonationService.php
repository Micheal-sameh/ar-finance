<?php

namespace App\Modules\Donations\Services;

use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalLineData;
use App\Enums\GlMappingRole;
use App\Enums\JournalSourceType;
use App\Models\User;
use App\Modules\Donations\DTOs\CreateDonationData;
use App\Modules\Donations\Enums\DonationPaymentMethod;
use App\Modules\Donations\Enums\DonationStatus;
use App\Modules\Donations\Models\Donation;
use App\Modules\Donations\Repositories\Contracts\DonationRepositoryInterface;
use App\Modules\Donations\Repositories\Contracts\DonorRepositoryInterface;
use App\Repositories\Contracts\CostCenterRepositoryInterface;
use App\Services\ExchangeRateService;
use App\Services\GlMappingService;
use App\Services\JournalService;
use App\Services\PaymentAccountService;
use App\Support\ArabicAmountInWords;
use App\Support\TenantContext;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Owns the donation lifecycle: recording it (and posting it to the ledger
 * at the chosen center in the same step), cancelling it with a reversal,
 * and preparing the data for the donor's receipt.
 */
class DonationService
{
    public function __construct(
        private readonly DonationRepositoryInterface $donations,
        private readonly DonorRepositoryInterface $donors,
        private readonly CostCenterRepositoryInterface $costCenters,
        private readonly JournalService $journals,
        private readonly GlMappingService $glMapping,
        private readonly ExchangeRateService $exchangeRates,
        private readonly TenantContext $tenantContext,
        private readonly PaymentAccountService $paymentAccounts,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return $this->donations->paginate($filters);
    }

    public function find(int $id): ?Donation
    {
        return $this->donations->find($id);
    }

    /**
     * The text printed at the bottom of every receipt telling donors how to
     * give without visiting (InstaPay address, bank account, …).
     */
    public function instructions(): ?string
    {
        return $this->tenantContext->tenant()?->donation_instructions;
    }

    public function hasActiveTenant(): bool
    {
        return $this->tenantContext->tenant() !== null;
    }

    /**
     * @throws RuntimeException when a Platform Admin is viewing all tenants
     */
    public function updateInstructions(?string $instructions): void
    {
        $tenant = $this->tenantContext->tenant();

        if ($tenant === null) {
            throw new RuntimeException('Switch into a church first — the receipt donation info belongs to one church.');
        }

        $tenant->update(['donation_instructions' => $instructions]);
    }

    public function baseCurrency(): string
    {
        return strtoupper($this->tenantContext->tenant()?->base_currency ?? 'EGP');
    }

    /**
     * Everything the donation form and filters offer: donors, centers,
     * currencies (the tenant's base first) and the payment methods.
     *
     * @return array{donors: array<int, array{id: int, name: string, phone: string|null}>, costCenters: array<int, array{id: int, name: string}>, currencies: array<int, array{code: string, name: string}>, baseCurrency: string, paymentMethods: array<int, array{value: string, label: string}>, paymentAccounts: array<string, array<int, array{id: int, label: string}>>, creators: array<int, array{id: int, name: string}>}
     */
    public function formOptions(): array
    {
        $base = $this->baseCurrency();
        $currencies = array_values(array_filter($this->exchangeRates->currencyOptions(), fn (array $option) => $option['code'] !== $base));
        array_unshift($currencies, ['code' => $base, 'name' => $base]);

        return [
            'donors' => $this->donors->all()->map(fn ($donor) => ['id' => $donor->id, 'name' => $donor->name, 'phone' => $donor->phone])->all(),
            'costCenters' => $this->costCenters->all()->map(fn ($center) => ['id' => $center->id, 'name' => $center->name])->all(),
            'currencies' => $currencies,
            'baseCurrency' => $base,
            'paymentMethods' => array_map(fn (DonationPaymentMethod $method) => ['value' => $method->value, 'label' => $method->label()], DonationPaymentMethod::cases()),
            'paymentAccounts' => $this->paymentAccounts->optionsByMethod(array_combine(
                array_map(fn (DonationPaymentMethod $method) => $method->value, DonationPaymentMethod::cases()),
                array_map(fn (DonationPaymentMethod $method) => $method->glRole(), DonationPaymentMethod::cases()),
            )),
            'creators' => $this->donations->creators()->map(fn ($user) => ['id' => $user->id, 'name' => $user->name])->all(),
        ];
    }

    /**
     * Records the donation and posts it: Dr the account mapped to its
     * payment method / Cr the donations income account, both at the chosen
     * center and in the tenant's base currency. Refused, with nothing saved,
     * if either mapping is missing.
     *
     * @throws RuntimeException
     */
    public function create(CreateDonationData $data, int $userId): Donation
    {
        $base = $this->baseCurrency();
        $currency = $data->currency ?? $base;
        $rate = $currency === $base ? 1.0 : ($data->exchangeRate ?? $this->exchangeRates->rateFor($currency, $data->donatedAt));

        if ($rate === null || $rate <= 0) {
            throw new RuntimeException("No exchange rate is available for {$currency} — enter the rate to {$base} yourself.");
        }

        $baseAmount = round($data->amount * $rate, 2);

        if ($baseAmount <= 0) {
            throw new RuntimeException('The donation is too small to record once converted to the base currency.');
        }

        $defaults = $this->glMapping->defaults();
        $paymentAccountId = $this->paymentAccounts->resolve($data->paymentMethod->value, $data->paymentMethod->glRole(), $data->paymentAccountId, $data->paymentMethod->label());
        $revenueAccountId = $defaults[GlMappingRole::DonationRevenue->value] ?? null;

        if ($revenueAccountId === null) {
            throw new RuntimeException('No donations income account is set — ask finance or an admin to set "'.GlMappingRole::DonationRevenue->label().'" in the General Ledger mapping.');
        }

        return DB::transaction(function () use ($data, $userId, $currency, $rate, $baseAmount, $paymentAccountId, $revenueAccountId) {
            $donation = $this->donations->create([
                'tenant_id' => $this->tenantContext->id(),
                'donor_id' => $data->donorId,
                'cost_center_id' => $data->costCenterId,
                'amount' => round($data->amount, 2),
                'currency' => $currency,
                'exchange_rate' => $rate,
                'base_amount' => $baseAmount,
                'donated_at' => $data->donatedAt,
                'payment_method' => $data->paymentMethod,
                'payment_reference' => $data->paymentReference,
                'payment_account_id' => $data->paymentAccountId,
                'status' => DonationStatus::Received,
                'notes' => $data->notes,
                'receipt_number' => $this->donations->nextReceiptNumber(),
                'created_by' => $userId,
            ]);

            $donorName = $donation->load('donor')->donor?->name ?? 'Anonymous';

            $this->journals->postJournalEntry(new CreateJournalEntryData(
                date: $data->donatedAt,
                description: "Donation #{$donation->receipt_number} — {$donorName}",
                reference: $data->paymentReference,
                sourceType: JournalSourceType::Donation,
                sourceId: $donation->id,
                createdBy: $userId,
                lines: [
                    new JournalLineData(accountId: $paymentAccountId, debit: $baseAmount, credit: 0, costCenterId: $data->costCenterId),
                    new JournalLineData(accountId: $revenueAccountId, debit: 0, credit: $baseAmount, costCenterId: $data->costCenterId),
                ],
            ));

            return $donation;
        });
    }

    /**
     * Platform Admins / Portal Managers (tenants.manage) cancel directly;
     * everyone else files a request they must approve.
     *
     * @throws RuntimeException
     */
    public function cancel(Donation $donation, User $user, ?string $reason): Donation
    {
        if ($user->can('tenants.manage')) {
            return $this->reverse($donation, $user->id);
        }

        return $this->requestCancel($donation, $user->id, $reason);
    }

    /**
     * Marks the donation as awaiting a cancellation decision. Nothing is
     * posted to the ledger until a Platform Admin / Portal Manager approves.
     *
     * @throws RuntimeException
     */
    public function requestCancel(Donation $donation, int $userId, ?string $reason): Donation
    {
        if ($donation->status !== DonationStatus::Received) {
            throw new RuntimeException($donation->status === DonationStatus::Cancelled
                ? 'This donation is already cancelled.'
                : 'A cancellation has already been requested for this donation.');
        }

        return $this->donations->update($donation, [
            'status' => DonationStatus::CancelRequested,
            'cancel_reason' => $reason,
            'cancel_requested_at' => now(),
            'cancel_requested_by' => $userId,
        ]);
    }

    /**
     * @throws RuntimeException
     */
    public function approveCancel(Donation $donation, int $userId): Donation
    {
        if ($donation->status !== DonationStatus::CancelRequested) {
            throw new RuntimeException('This donation has no cancellation request to approve.');
        }

        return $this->reverse($donation, $userId);
    }

    /**
     * @throws RuntimeException
     */
    public function rejectCancel(Donation $donation): Donation
    {
        if ($donation->status !== DonationStatus::CancelRequested) {
            throw new RuntimeException('This donation has no cancellation request to reject.');
        }

        return $this->donations->update($donation, [
            'status' => DonationStatus::Received,
            'cancel_reason' => null,
            'cancel_requested_at' => null,
            'cancel_requested_by' => null,
        ]);
    }

    /**
     * Cancels the donation: each journal entry posted for it is reversed as
     * its own entry (debits and credits swapped, same accounts and center),
     * so the money and income come back out of the books exactly where they
     * went in.
     *
     * @throws RuntimeException
     */
    public function reverse(Donation $donation, int $userId): Donation
    {
        if ($donation->status === DonationStatus::Cancelled) {
            throw new RuntimeException('This donation is already cancelled.');
        }

        return DB::transaction(function () use ($donation, $userId) {
            foreach ($this->donations->journalEntries($donation) as $entry) {
                $this->journals->postJournalEntry(new CreateJournalEntryData(
                    date: now()->toDateString(),
                    description: "Cancelled Donation #{$donation->receipt_number} — reversal of: {$entry->description}",
                    reference: $entry->reference,
                    sourceType: JournalSourceType::Donation,
                    sourceId: $donation->id,
                    createdBy: $userId,
                    lines: $entry->lines->map(fn ($line) => new JournalLineData(
                        accountId: $line->account_id,
                        debit: (float) $line->credit,
                        credit: (float) $line->debit,
                        costCenterId: $line->cost_center_id,
                    ))->all(),
                ));
            }

            return $this->donations->update($donation, [
                'status' => DonationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $userId,
            ]);
        });
    }

    /**
     * What the Arabic receipt shows. A cancelled donation has no receipt.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function receiptData(Donation $donation): array
    {
        if ($donation->status === DonationStatus::Cancelled) {
            throw new RuntimeException('A cancelled donation has no receipt.');
        }

        $donation->loadMissing(['donor', 'costCenter', 'creator', 'tenant']);
        $amount = (float) $donation->amount;

        return [
            'tenantName' => $donation->tenant?->name,
            'instructions' => $donation->tenant?->donation_instructions,
            'churchLogoPath' => resource_path('js/assets/church-logo.png'),
            'receiptNumber' => str_pad((string) $donation->receipt_number, 6, '0', STR_PAD_LEFT),
            'date' => $donation->donated_at->format('Y-m-d'),
            'donorName' => $donation->donor?->name ?? 'ابناء الانبا رويس',
            'amountNumber' => number_format($amount, 2),
            'currency' => $donation->currency,
            'currencyName' => ArabicAmountInWords::currencyName($donation->currency),
            'amountWords' => ArabicAmountInWords::convert($amount, $donation->currency),
            'centerName' => $donation->costCenter?->name,
            'paymentMethod' => match ($donation->payment_method) {
                DonationPaymentMethod::Cash => 'نقداً',
                DonationPaymentMethod::BankTransfer => 'تحويل بنكي',
                DonationPaymentMethod::Pos => 'ماكينة نقاط البيع',
                DonationPaymentMethod::Instapay => 'إنستاباي',
            },
            'paymentReference' => $donation->payment_reference,
            'receivedBy' => $donation->creator?->name,
        ];
    }

    public function receiptFilename(Donation $donation): string
    {
        return 'donation-receipt-'.str_pad((string) $donation->receipt_number, 6, '0', STR_PAD_LEFT).'.pdf';
    }
}
