<?php

namespace App\Modules\Treasury\Services;

use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalLineData;
use App\Enums\AccountType;
use App\Enums\GlMappingRole;
use App\Enums\JournalSourceType;
use App\Enums\TenantModule;
use App\Models\Account;
use App\Models\GlMapping;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Treasury\DTOs\TreasuryTransferData;
use App\Modules\Treasury\Enums\TreasuryTransferStatus;
use App\Modules\Treasury\Enums\TreasuryTransferType;
use App\Modules\Treasury\Models\TreasuryTransfer;
use App\Modules\Treasury\Repositories\Contracts\TreasuryTransferRepositoryInterface;
use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Services\GlMappingService;
use App\Services\JournalService;
use App\Support\TenantContext;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The treasurer's (أمين الصندوق) books. Tenants hand money to the treasury,
 * ask for it back, are funded from it and repay; the treasurer also moves
 * money between the safe and the banks.
 *
 * Every posting is a pair of balanced entries — one in the tenant's ledger,
 * one in the treasury tenant's ledger — written together in one transaction
 * (TenantContext::runAs switches ledgers). An internal move is a single
 * entry in the treasury ledger. Held and advanced amounts per tenant are
 * derived from the accepted transfers, so they can always be tied back to
 * the "due from / due to treasury" balances in the tenant's own books.
 */
class TreasuryService
{
    public function __construct(
        private readonly TreasuryTransferRepositoryInterface $transfers,
        private readonly AccountRepositoryInterface $accounts,
        private readonly JournalService $journals,
        private readonly GlMappingService $glMapping,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return $this->transfers->paginate($filters);
    }

    public function find(int $id): ?TreasuryTransfer
    {
        return $this->transfers->find($id);
    }

    public function isTreasuryTenant(): bool
    {
        return (bool) $this->tenantContext->tenant()?->is_treasury;
    }

    /**
     * Everything the treasury screens show: for the treasurer the locations
     * with their balances and every tenant's position tied to its own books;
     * for a tenant just what the treasury holds for it and what it owes.
     *
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $tenant = $this->tenantContext->tenant();

        if ($tenant === null) {
            return ['mode' => 'overview'];
        }

        if ($tenant->is_treasury) {
            return [
                'mode' => 'treasury',
                'locations' => $this->locations(),
                'positions' => $this->positions(),
            ];
        }

        return [
            'mode' => 'tenant',
            'held' => $this->transfers->heldFor($tenant->id),
            'owed' => $this->transfers->advancedTo($tenant->id),
        ];
    }

    /**
     * What the forms offer: the caller's own cash/bank accounts (with the
     * mapped default first) for a tenant, the safe/bank locations and the
     * eligible tenants for the treasurer.
     *
     * @return array<string, mixed>
     */
    public function formOptions(): array
    {
        $tenant = $this->tenantContext->tenant();

        if ($tenant === null) {
            return ['accounts' => [], 'defaultAccountId' => null, 'locations' => [], 'tenants' => []];
        }

        if ($tenant->is_treasury) {
            return [
                'accounts' => [],
                'defaultAccountId' => null,
                'locations' => $this->locations(),
                'tenants' => Tenant::query()
                    ->where('is_active', true)->where('is_treasury', false)
                    ->orderBy('name')->get(['id', 'name', 'modules'])
                    ->filter(fn (Tenant $candidate) => $candidate->hasModule(TenantModule::Treasury))
                    ->map(fn (Tenant $candidate) => ['id' => $candidate->id, 'name' => $candidate->name])
                    ->values()->all(),
            ];
        }

        return [
            'accounts' => $this->assetAccounts()->map(fn (Account $account) => ['id' => $account->id, 'label' => "{$account->code} {$account->name}"])->values()->all(),
            'defaultAccountId' => $this->glMapping->defaults()[GlMappingRole::TreasuryCash->value] ?? null,
            'locations' => [],
            'tenants' => [],
        ];
    }

    /**
     * A tenant admin asks to deposit, withdraw or repay. Nothing is posted
     * until the treasurer accepts.
     *
     * @throws RuntimeException
     */
    public function request(TreasuryTransferData $data, User $user): TreasuryTransfer
    {
        if (! $data->type->needsAcceptance()) {
            throw new RuntimeException('Only deposits, withdrawals and repayments are requested from the treasurer.');
        }

        $tenant = $this->tenantContext->tenant();
        $this->assertEligibleTenant($tenant);
        $treasury = $this->treasuryOrFail();

        $this->assertAssetAccount($data->tenantAccountId, 'cash/bank account');

        match ($data->type) {
            TreasuryTransferType::Withdrawal => $this->assertAtMost($data->amount, $this->transfers->heldFor($tenant->id), 'The treasury only holds %s for you'),
            TreasuryTransferType::Repayment => $this->assertAtMost($data->amount, $this->transfers->advancedTo($tenant->id), 'You only owe the treasury %s'),
            default => null,
        };

        return $this->transfers->create([
            'treasury_tenant_id' => $treasury->id,
            'tenant_id' => $tenant->id,
            'type' => $data->type,
            'status' => TreasuryTransferStatus::Requested,
            'requested_amount' => $data->amount,
            'transfer_date' => $data->date,
            'tenant_account_id' => $data->tenantAccountId,
            'reference' => $data->reference,
            'notes' => $data->notes,
            'requested_by' => $user->id,
        ]);
    }

    /**
     * The treasurer funds a tenant out of a safe or bank. It is an advance
     * the tenant owes back, so it never touches what the treasury holds for
     * other tenants, and posts at once.
     *
     * @throws RuntimeException
     */
    public function fund(TreasuryTransferData $data, User $user): TreasuryTransfer
    {
        $this->assertTreasurySide();

        $tenant = Tenant::query()->find($data->tenantId);
        $this->assertEligibleTenant($tenant);

        $treasury = $this->tenantContext->tenant();
        $tenantAccountId = $this->roleAccount($tenant->id, GlMappingRole::TreasuryCash)
            ?? throw new RuntimeException("{$tenant->name} has no \"".GlMappingRole::TreasuryCash->label().'" account set in its General Ledger mapping, so the funding has nowhere to arrive.');

        return DB::transaction(function () use ($data, $user, $treasury, $tenant, $tenantAccountId) {
            $this->lockTreasuryLedger();
            $transfer = $this->transfers->create([
                'treasury_tenant_id' => $treasury->id,
                'tenant_id' => $tenant->id,
                'type' => TreasuryTransferType::Funding,
                'status' => TreasuryTransferStatus::Accepted,
                'requested_amount' => $data->amount,
                'accepted_amount' => $data->amount,
                'transfer_date' => $data->date,
                'tenant_account_id' => $tenantAccountId,
                'treasury_account_id' => $data->treasuryAccountId,
                'reference' => $data->reference,
                'notes' => $data->notes,
                'requested_by' => $user->id,
                'decided_by' => $user->id,
                'decided_at' => now(),
            ]);

            $this->post($transfer, $data->amount, $user->id);

            return $transfer;
        });
    }

    /**
     * Moves money between two of the treasury's own accounts — cash to bank
     * or bank to cash — as one entry in the treasury ledger.
     *
     * @throws RuntimeException
     */
    public function move(TreasuryTransferData $data, User $user): TreasuryTransfer
    {
        $this->assertTreasurySide();

        if ($data->treasuryAccountId === $data->counterAccountId) {
            throw new RuntimeException('Choose two different accounts to move money between.');
        }

        $treasury = $this->tenantContext->tenant();

        return DB::transaction(function () use ($data, $user, $treasury) {
            $this->lockTreasuryLedger();
            $transfer = $this->transfers->create([
                'treasury_tenant_id' => $treasury->id,
                'tenant_id' => null,
                'type' => TreasuryTransferType::Move,
                'status' => TreasuryTransferStatus::Accepted,
                'requested_amount' => $data->amount,
                'accepted_amount' => $data->amount,
                'transfer_date' => $data->date,
                'treasury_account_id' => $data->treasuryAccountId,
                'counter_account_id' => $data->counterAccountId,
                'reference' => $data->reference,
                'notes' => $data->notes,
                'requested_by' => $user->id,
                'decided_by' => $user->id,
                'decided_at' => now(),
            ]);

            $this->post($transfer, $data->amount, $user->id);

            return $transfer;
        });
    }

    /**
     * The treasurer accepts a request for the amount actually counted and
     * the safe/bank it goes into (or comes out of), posting both ledgers.
     *
     * @throws RuntimeException
     */
    public function accept(int $transferId, float $amount, int $treasuryAccountId, User $user): TreasuryTransfer
    {
        $this->assertTreasurySide();

        return DB::transaction(function () use ($transferId, $amount, $treasuryAccountId, $user) {
            $this->lockTreasuryLedger();
            $transfer = $this->lockedTransfer($transferId);

            if (! $transfer->status->isDecidable()) {
                throw new RuntimeException('This request has already been decided.');
            }

            if ($amount <= 0 || $amount > (float) $transfer->requested_amount) {
                throw new RuntimeException('The accepted amount must be more than zero and no more than the '.number_format((float) $transfer->requested_amount, 2).' requested.');
            }

            $transfer->treasury_account_id = $treasuryAccountId;
            $transfer->accepted_amount = $amount;
            $transfer->status = TreasuryTransferStatus::Accepted;
            $transfer->decided_by = $user->id;
            $transfer->decided_at = now();

            $this->post($transfer, $amount, $user->id);
            $transfer->save();

            return $transfer;
        });
    }

    /**
     * @throws RuntimeException
     */
    public function reject(int $transferId, ?string $reason, User $user): TreasuryTransfer
    {
        $this->assertTreasurySide();

        return DB::transaction(function () use ($transferId, $reason, $user) {
            $transfer = $this->lockedTransfer($transferId);

            if (! $transfer->status->isDecidable()) {
                throw new RuntimeException('This request has already been decided.');
            }

            return $this->transfers->update($transfer, [
                'status' => TreasuryTransferStatus::Rejected,
                'reject_reason' => $reason,
                'decided_by' => $user->id,
                'decided_at' => now(),
            ]);
        });
    }

    /**
     * Cancels an accepted transfer: every entry it posted, in either ledger,
     * is reversed as its own entry. Refused if undoing it would leave the
     * treasury short of cash or a tenant with a negative balance.
     *
     * @throws RuntimeException
     */
    public function void(int $transferId, User $user): TreasuryTransfer
    {
        $this->assertTreasurySide();

        return DB::transaction(function () use ($transferId, $user) {
            $this->lockTreasuryLedger();
            $transfer = $this->lockedTransfer($transferId);

            if (! $transfer->status->isVoidable()) {
                throw new RuntimeException('Only an accepted transfer can be voided.');
            }

            $this->assertCanUndo($transfer);

            foreach ($this->transfers->journalEntries($transfer) as $entry) {
                $this->tenantContext->runAs($entry->tenant_id, fn () => $this->journals->postJournalEntry(new CreateJournalEntryData(
                    date: now()->toDateString(),
                    description: "Voided treasury {$transfer->type->label()} #{$transfer->id} — reversal of: {$entry->description}",
                    reference: $entry->reference,
                    sourceType: JournalSourceType::Treasury,
                    sourceId: $transfer->id,
                    createdBy: $user->id,
                    lines: $entry->lines->map(fn ($line) => new JournalLineData(
                        accountId: $line->account_id,
                        debit: (float) $line->credit,
                        credit: (float) $line->debit,
                        costCenterId: $line->cost_center_id,
                    ))->all(),
                )));
            }

            return $this->transfers->update($transfer, [
                'status' => TreasuryTransferStatus::Voided,
                'voided_by' => $user->id,
                'voided_at' => now(),
            ]);
        });
    }

    /**
     * Writes the transfer's journal entries: the tenant side (unless it is
     * an internal move) and the treasury side, each balanced on its own.
     *
     * @throws RuntimeException
     */
    private function post(TreasuryTransfer $transfer, float $amount, int $userId): void
    {
        $treasuryId = $transfer->treasury_tenant_id;
        $tenantId = $transfer->tenant_id;
        $location = $this->assetAccountIn($treasuryId, $transfer->treasury_account_id, 'safe/bank');

        $treasuryDebit = null;
        $treasuryCredit = null;
        $tenantDebit = null;
        $tenantCredit = null;

        switch ($transfer->type) {
            case TreasuryTransferType::Deposit:
                $tenantAccount = $this->assetAccountIn($tenantId, $transfer->tenant_account_id, 'cash/bank');
                $this->assertSameCurrency($tenantAccount, $location);
                [$tenantDebit, $tenantCredit] = [$this->requiredRole($tenantId, GlMappingRole::TreasuryReceivable), $tenantAccount->id];
                [$treasuryDebit, $treasuryCredit] = [$location->id, $this->requiredRole($treasuryId, GlMappingRole::TreasuryHeld)];
                break;

            case TreasuryTransferType::Withdrawal:
                $tenantAccount = $this->assetAccountIn($tenantId, $transfer->tenant_account_id, 'cash/bank');
                $this->assertSameCurrency($tenantAccount, $location);
                $this->assertAtMost($amount, $this->transfers->heldFor($tenantId), 'The treasury only holds %s for this tenant');
                $this->assertLocationCovers($location, $amount);
                [$tenantDebit, $tenantCredit] = [$tenantAccount->id, $this->requiredRole($tenantId, GlMappingRole::TreasuryReceivable)];
                [$treasuryDebit, $treasuryCredit] = [$this->requiredRole($treasuryId, GlMappingRole::TreasuryHeld), $location->id];
                break;

            case TreasuryTransferType::Funding:
                $tenantAccount = $this->assetAccountIn($tenantId, $transfer->tenant_account_id, 'cash/bank');
                $this->assertSameCurrency($tenantAccount, $location);
                $this->assertLocationCovers($location, $amount);
                [$tenantDebit, $tenantCredit] = [$tenantAccount->id, $this->requiredRole($tenantId, GlMappingRole::TreasuryPayable)];
                [$treasuryDebit, $treasuryCredit] = [$this->requiredRole($treasuryId, GlMappingRole::TreasuryAdvances), $location->id];
                break;

            case TreasuryTransferType::Repayment:
                $tenantAccount = $this->assetAccountIn($tenantId, $transfer->tenant_account_id, 'cash/bank');
                $this->assertSameCurrency($tenantAccount, $location);
                $this->assertAtMost($amount, $this->transfers->advancedTo($tenantId), 'This tenant only owes the treasury %s');
                [$tenantDebit, $tenantCredit] = [$this->requiredRole($tenantId, GlMappingRole::TreasuryPayable), $tenantAccount->id];
                [$treasuryDebit, $treasuryCredit] = [$location->id, $this->requiredRole($treasuryId, GlMappingRole::TreasuryAdvances)];
                break;

            case TreasuryTransferType::Move:
                $destination = $this->assetAccountIn($treasuryId, $transfer->counter_account_id, 'destination safe/bank');
                $this->assertSameCurrency($location, $destination);
                $this->assertLocationCovers($location, $amount);
                [$treasuryDebit, $treasuryCredit] = [$destination->id, $location->id];
                break;
        }

        $date = $transfer->transfer_date->toDateString();
        $label = "Treasury {$transfer->type->label()} #{$transfer->id}";

        if ($tenantDebit !== null) {
            $this->postPair($tenantId, $date, "{$label} — {$this->tenantName($tenantId)} ↔ treasury", $transfer, $userId, $tenantDebit, $tenantCredit, $amount);
        }

        $counterparty = $tenantId !== null ? $this->tenantName($tenantId) : 'safe/bank';
        $this->postPair($treasuryId, $date, "{$label} — {$counterparty}", $transfer, $userId, $treasuryDebit, $treasuryCredit, $amount);
    }

    private function postPair(?int $tenantId, string $date, string $description, TreasuryTransfer $transfer, int $userId, int $debitAccountId, int $creditAccountId, float $amount): void
    {
        $this->tenantContext->runAs($tenantId, fn () => $this->journals->postJournalEntry(new CreateJournalEntryData(
            date: $date,
            description: $description,
            reference: $transfer->reference,
            sourceType: JournalSourceType::Treasury,
            sourceId: $transfer->id,
            createdBy: $userId,
            lines: [
                new JournalLineData(accountId: $debitAccountId, debit: $amount, credit: 0),
                new JournalLineData(accountId: $creditAccountId, debit: 0, credit: $amount),
            ],
        )));
    }

    /**
     * @throws RuntimeException
     */
    private function assertCanUndo(TreasuryTransfer $transfer): void
    {
        $amount = $transfer->amount();

        switch ($transfer->type) {
            case TreasuryTransferType::Deposit:
                $this->assertAtMost($amount, $this->transfers->heldFor($transfer->tenant_id), 'Voiding this would leave the treasury holding less than nothing: it holds only %s for the tenant');
                $this->assertLocationCovers($this->accountIn($transfer->treasury_tenant_id, $transfer->treasury_account_id), $amount);
                break;
            case TreasuryTransferType::Repayment:
                $this->assertLocationCovers($this->accountIn($transfer->treasury_tenant_id, $transfer->treasury_account_id), $amount);
                break;
            case TreasuryTransferType::Funding:
                $this->assertAtMost($amount, $this->transfers->advancedTo($transfer->tenant_id), 'Voiding this would leave a negative advance: the tenant owes only %s');
                break;
            case TreasuryTransferType::Move:
                $this->assertLocationCovers($this->accountIn($transfer->treasury_tenant_id, $transfer->counter_account_id), $amount);
                break;
            default:
                break;
        }
    }

    private function assertAtMost(float $amount, float $limit, string $message): void
    {
        if (round($amount - $limit, 2) > 0) {
            throw new RuntimeException(sprintf($message, number_format($limit, 2)).'.');
        }
    }

    private function assertLocationCovers(Account $location, float $amount): void
    {
        $balance = $this->transfers->ledgerBalance($location->id);

        if (round($amount - $balance, 2) > 0) {
            throw new RuntimeException("{$location->code} {$location->name} only has ".number_format($balance, 2).' — not enough for '.number_format($amount, 2).'.');
        }
    }

    private function assertSameCurrency(Account $first, Account $second): void
    {
        if (strtoupper((string) $first->currency) !== strtoupper((string) $second->currency)) {
            throw new RuntimeException("{$first->code} is in {$first->currency} but {$second->code} is in {$second->currency} — a treasury transfer must stay in one currency.");
        }
    }

    private function assertEligibleTenant(?Tenant $tenant): void
    {
        if ($tenant === null || ! $tenant->is_active) {
            throw new RuntimeException('Switch into an active service first.');
        }

        if ($tenant->is_treasury) {
            throw new RuntimeException('The treasury cannot deposit with itself.');
        }

        if (! $tenant->hasModule(TenantModule::Treasury)) {
            throw new RuntimeException("{$tenant->name} does not use the treasury.");
        }
    }

    private function assertTreasurySide(): void
    {
        if (! $this->isTreasuryTenant()) {
            throw new RuntimeException('Only the treasury can do this.');
        }
    }

    private function treasuryOrFail(): Tenant
    {
        return $this->transfers->treasuryTenant()
            ?? throw new RuntimeException('No treasury is set up yet — ask a Platform Admin to mark one tenant as the treasury.');
    }

    private function lockedTransfer(int $id): TreasuryTransfer
    {
        return $this->transfers->findForUpdate($id) ?? throw new RuntimeException('That transfer no longer exists.');
    }

    private function tenantName(?int $tenantId): string
    {
        return (string) Tenant::query()->whereKey($tenantId)->value('name');
    }

    private function roleAccount(int $tenantId, GlMappingRole $role): ?int
    {
        return $this->tenantContext->runAs($tenantId, fn () => $this->glMapping->defaults()[$role->value] ?? null);
    }

    private function requiredRole(int $tenantId, GlMappingRole $role): int
    {
        return $this->roleAccount($tenantId, $role)
            ?? throw new RuntimeException("{$this->tenantName($tenantId)} has no \"{$role->label()}\" account set in its General Ledger mapping.");
    }

    private function accountIn(int $tenantId, ?int $accountId): Account
    {
        $account = $accountId === null ? null : $this->tenantContext->runAs($tenantId, fn () => $this->accounts->find($accountId));

        return $account ?? throw new RuntimeException('That account no longer exists.');
    }

    private function assetAccountIn(?int $tenantId, ?int $accountId, string $what): Account
    {
        $account = $tenantId === null || $accountId === null
            ? null
            : $this->tenantContext->runAs($tenantId, fn () => $this->accounts->find($accountId));

        if ($account === null) {
            throw new RuntimeException("Choose the {$what} account.");
        }

        $this->assertSuitableAsset($account);

        return $account;
    }

    private function assertAssetAccount(?int $accountId, string $what): void
    {
        $account = $accountId === null ? null : $this->accounts->find($accountId);

        if ($account === null) {
            throw new RuntimeException("Choose the {$what}.");
        }

        $this->assertSuitableAsset($account);
    }

    private function assertSuitableAsset(Account $account): void
    {
        if ($account->type !== AccountType::Asset || ! $account->is_active || $account->children()->exists()) {
            throw new RuntimeException("{$account->code} {$account->name} must be an active cash or bank (asset) account that can be posted to.");
        }

        if (in_array($account->id, $this->reservedAccountIds($account->tenant_id), true)) {
            throw new RuntimeException("{$account->code} {$account->name} is a control account (receivables, fixed assets or a treasury balance), not a cash or bank account.");
        }
    }

    /**
     * Asset accounts that are not money held: customer receivables, fixed
     * assets and the treasury's own control accounts. They can never be the
     * cash side of a treasury transfer — it would tie the books in knots.
     *
     * @return array<int, int>
     */
    private function reservedAccountIds(int $tenantId): array
    {
        return GlMapping::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('role', [
                GlMappingRole::Receivable->value,
                GlMappingRole::TaxReceivable->value,
                GlMappingRole::FixedAsset->value,
                GlMappingRole::AccumulatedDepreciation->value,
                GlMappingRole::TreasuryReceivable->value,
                GlMappingRole::TreasuryAdvances->value,
            ])
            ->pluck('account_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Serialises everything that spends the treasury's cash. Without it two
     * treasurers accepting at the same moment could each see enough money in
     * the safe and together overdraw it. Call first inside the transaction.
     */
    private function lockTreasuryLedger(): void
    {
        Tenant::query()->whereKey($this->tenantContext->id())->lockForUpdate()->first();
    }

    /**
     * Active, postable cash/bank-type asset accounts of the current tenant.
     *
     * @return Collection<int, Account>
     */
    private function assetAccounts(): Collection
    {
        $all = $this->accounts->all();
        $parentIds = $all->pluck('parent_id')->filter()->unique();
        $reserved = $this->reservedAccountIds((int) $this->tenantContext->id());

        return $all
            ->filter(fn (Account $account) => $account->type === AccountType::Asset && ! $parentIds->contains($account->id) && ! in_array($account->id, $reserved, true))
            ->sortBy('code')
            ->values();
    }

    /**
     * The treasury's safe/bank accounts with what each currently holds.
     *
     * @return array<int, array{id: int, code: string, name: string, currency: string|null, balance: float}>
     */
    private function locations(): array
    {
        return $this->assetAccounts()
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'currency' => $account->currency,
                'balance' => $this->transfers->ledgerBalance($account->id),
            ])
            ->all();
    }

    /**
     * Per tenant: what the treasury says it holds / is owed, next to what the
     * tenant's own ledger says — the two must agree.
     *
     * @return array<int, array{tenant_id: int, tenant_name: string, held: float, advanced: float, tenant_due_from: float|null, tenant_due_to: float|null, matches: bool}>
     */
    private function positions(): array
    {
        $names = Tenant::query()->pluck('name', 'id');

        return collect($this->transfers->positionsByTenant())
            ->map(function (array $position) use ($names) {
                $dueFrom = $this->tenantBalance($position['tenant_id'], GlMappingRole::TreasuryReceivable, debitNormal: true);
                $dueTo = $this->tenantBalance($position['tenant_id'], GlMappingRole::TreasuryPayable, debitNormal: false);

                return [
                    'tenant_id' => $position['tenant_id'],
                    'tenant_name' => (string) ($names[$position['tenant_id']] ?? ''),
                    'held' => round($position['held'], 2),
                    'advanced' => round($position['advanced'], 2),
                    'tenant_due_from' => $dueFrom,
                    'tenant_due_to' => $dueTo,
                    'matches' => $dueFrom === round($position['held'], 2) && $dueTo === round($position['advanced'], 2),
                ];
            })
            ->sortBy('tenant_name')
            ->values()
            ->all();
    }

    private function tenantBalance(int $tenantId, GlMappingRole $role, bool $debitNormal): ?float
    {
        $accountId = $this->roleAccount($tenantId, $role);

        if ($accountId === null) {
            return null;
        }

        $net = $this->transfers->ledgerBalance($accountId);

        return round($debitNormal ? $net : -$net, 2);
    }
}
