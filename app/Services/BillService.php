<?php

namespace App\Services;

use App\DTOs\BillLineData;
use App\DTOs\CreateBillData;
use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalLineData;
use App\Enums\JournalSourceType;
use App\Models\Bill;
use App\Models\PurchaseOrder;
use App\Repositories\Contracts\BillRepositoryInterface;
use App\Support\TenantContext;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The AP mirror of InvoiceService: draft → approve (posts to the GL) →
 * markPaid (a second, separate journal entry). See InvoiceService for the
 * AR side of the same pattern, including the VAT-split rationale.
 *
 * Bills are single-currency (the tenant's base currency) for now —
 * unlike Invoice, there's no currency/exchange_rate here yet. Revisit if
 * AP needs foreign-currency vendor bills.
 */
class BillService
{
    public function __construct(
        private readonly BillRepositoryInterface $bills,
        private readonly JournalService $journals,
    ) {}

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->bills->paginate($filters, $perPage);
    }

    public function find(int $id): ?Bill
    {
        return $this->bills->find($id);
    }

    /**
     * The bills list: each row is the bill plus, when a Platform Admin is
     * looking across every service, the name of the service it belongs to.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listing(array $filters, bool $withServiceName): LengthAwarePaginator
    {
        return $this->bills->paginate($filters)->through(fn (Bill $bill) => [
            ...$bill->toArray(),
            'tenant_name' => $withServiceName ? $bill->tenant?->name : null,
        ]);
    }

    /**
     * Spreadsheet rows for the current filters: number, vendor, due date,
     * status, tax-inclusive total, paid so far and balance due.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<int, mixed>>
     */
    public function exportRows(array $filters, int $limit): array
    {
        return collect($this->bills->paginate($filters, $limit)->items())
            ->map(fn (Bill $bill) => [
                $bill->bill_number,
                $bill->vendor?->name,
                $bill->due_date->toDateString(),
                $bill->status->label(),
                $bill->total(),
                $bill->amountPaid(),
                $bill->status->isPayable() ? $bill->balanceDue() : null,
            ])
            ->all();
    }

    /**
     * The bill with everything the printable PDF shows.
     */
    public function findForPdf(int $id): ?Bill
    {
        return $this->bills->find($id)?->load('tenant');
    }

    /**
     * Drafts don't touch the ledger — only approve() does.
     */
    public function create(CreateBillData $data): Bill
    {
        return $this->bills->create(
            attributes: [
                'tenant_id' => app(TenantContext::class)->id(),
                'vendor_id' => $data->vendorId,
                'purchase_order_id' => $data->purchaseOrderId,
                'bill_number' => $data->billNumber,
                'bill_date' => $data->billDate,
                'due_date' => $data->dueDate,
                'payable_account_id' => $data->payableAccountId,
                'tax_receivable_account_id' => $data->taxReceivableAccountId,
                'cost_center_id' => $data->costCenterId,
                'status' => 'draft',
            ],
            lines: array_map(fn (BillLineData $line) => [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice,
                'tax_rate' => $line->taxRate,
                'account_id' => $line->accountId,
            ], $data->lines),
        );
    }

    /**
     * Prefills a draft bill from a purchase order's vendor and lines —
     * the PO itself never posts to the ledger, only the bill created
     * from it does, once approved. POs carry no tax_rate, so lines start
     * untaxed; edit the bill to add tax before approving if needed.
     */
    public function createFromPurchaseOrder(
        PurchaseOrder $purchaseOrder,
        string $billNumber,
        string $billDate,
        string $dueDate,
        int $payableAccountId,
    ): Bill {
        return $this->create(new CreateBillData(
            vendorId: $purchaseOrder->vendor_id,
            purchaseOrderId: $purchaseOrder->id,
            billNumber: $billNumber,
            billDate: $billDate,
            dueDate: $dueDate,
            payableAccountId: $payableAccountId,
            taxReceivableAccountId: null,
            costCenterId: null,
            lines: $purchaseOrder->lines->map(fn ($line) => new BillLineData(
                description: $line->description,
                quantity: (float) $line->quantity,
                unitPrice: (float) $line->unit_price,
                taxRate: 0.0,
                accountId: $line->account_id,
            ))->all(),
        ));
    }

    /**
     * Recognizes the expense: debits each line's account for its pre-tax
     * subtotal, debits the recoverable input-VAT portion separately to
     * tax_receivable_account_id (an asset, not folded into the expense),
     * credits the bill's payable control account for the tax-inclusive
     * total. The payable credit is the *sum* of the debit lines, so
     * per-line rounding can't throw the entry out of balance.
     */
    public function approve(Bill $bill): Bill
    {
        if (! $bill->status->isEditable()) {
            throw new RuntimeException("Bill {$bill->bill_number} has already been approved.");
        }

        $totalTax = $bill->totalTax();

        if ($totalTax > 0 && ! $bill->tax_receivable_account_id) {
            throw new RuntimeException("Bill {$bill->bill_number} has tax on its lines but no tax receivable account was set.");
        }

        return DB::transaction(function () use ($bill) {
            $debitLines = $bill->lines->map(fn ($line) => new JournalLineData(
                accountId: $line->account_id,
                debit: $line->subtotal(),
                credit: 0,
                costCenterId: $bill->cost_center_id,
                description: $line->description,
            ))->all();

            if ($bill->totalTax() > 0) {
                $debitLines[] = new JournalLineData(
                    accountId: $bill->tax_receivable_account_id,
                    debit: $bill->totalTax(),
                    credit: 0,
                    description: "Tax on bill {$bill->bill_number}",
                );
            }

            $payableTotal = round(
                array_sum(array_map(fn (JournalLineData $line) => $line->debit, $debitLines)),
                2,
            );

            $lines = [
                ...$debitLines,
                new JournalLineData(
                    accountId: $bill->payable_account_id,
                    debit: 0,
                    credit: $payableTotal,
                    description: "Bill {$bill->bill_number}",
                ),
            ];

            $this->journals->postJournalEntry(new CreateJournalEntryData(
                date: $bill->bill_date->toDateString(),
                description: "Bill {$bill->bill_number} from {$bill->vendor->name}",
                reference: $bill->bill_number,
                sourceType: JournalSourceType::Expense,
                sourceId: $bill->id,
                createdBy: auth()->id(),
                lines: $lines,
            ));

            return $this->bills->updateStatus($bill, 'approved');
        });
    }

    /**
     * Pays $amount (the whole remaining balance by default) against an
     * approved or partially paid bill. Each payment is its own journal
     * entry — Dr the bill's payable account, Cr the account paid from —
     * and its own bill_payments row, so the bill shows what was paid when.
     * The bill becomes Paid only when nothing is left owing.
     *
     * @throws RuntimeException
     */
    public function recordPayment(Bill $bill, int $paymentAccountId, ?float $amount = null, ?string $paidOn = null, ?string $reference = null): Bill
    {
        return DB::transaction(function () use ($bill, $paymentAccountId, $amount, $paidOn, $reference) {
            $bill = $this->bills->findForPayment($bill->id) ?? throw new RuntimeException('Bill not found.');

            if (! $bill->status->isPayable()) {
                throw new RuntimeException("Bill {$bill->bill_number} is not awaiting payment.");
            }

            $balance = $bill->balanceDue();
            $amount = round($amount ?? $balance, 2);
            $paidOn ??= now()->toDateString();

            if ($amount <= 0) {
                throw new RuntimeException('The payment amount must be greater than zero.');
            }

            if ($amount > $balance + 0.005) {
                throw new RuntimeException("Bill {$bill->bill_number} only has {$this->money($balance)} left to pay.");
            }

            if ($paidOn < $bill->bill_date->toDateString()) {
                throw new RuntimeException("A payment cannot be dated before the bill date ({$bill->bill_date->toDateString()}).");
            }

            $entry = $this->journals->postJournalEntry(new CreateJournalEntryData(
                date: $paidOn,
                description: "Payment for bill {$bill->bill_number}",
                reference: $reference ?? $bill->bill_number,
                sourceType: JournalSourceType::Expense,
                sourceId: $bill->id,
                createdBy: auth()->id(),
                lines: [
                    new JournalLineData(accountId: $bill->payable_account_id, debit: $amount, credit: 0),
                    new JournalLineData(accountId: $paymentAccountId, debit: 0, credit: $amount),
                ],
            ));

            $this->bills->createPayment($bill, [
                'tenant_id' => $bill->tenant_id,
                'payment_account_id' => $paymentAccountId,
                'amount' => $amount,
                'paid_on' => $paidOn,
                'reference' => $reference,
                'journal_entry_id' => $entry->id,
                'created_by' => auth()->id(),
            ]);

            $settled = abs($balance - $amount) < 0.005;

            return $this->bills->updateStatus(
                $bill,
                $settled ? 'paid' : 'partially_paid',
                $settled ? Carbon::parse($paidOn) : null,
            );
        });
    }

    /**
     * Pays the full remaining balance of every selected bill from one
     * account, all or nothing: if any bill can't be paid, none are.
     *
     * @param  array<int, int>  $billIds
     * @return int how many bills were paid
     *
     * @throws RuntimeException
     */
    public function payMany(array $billIds, int $paymentAccountId, ?string $paidOn = null, ?string $reference = null): int
    {
        $bills = $this->bills->payableByIds($billIds);

        if ($bills->count() !== count(array_unique($billIds))) {
            throw new RuntimeException('Only approved or partially paid bills can be paid. Refresh the list and try again.');
        }

        DB::transaction(function () use ($bills, $paymentAccountId, $paidOn, $reference) {
            foreach ($bills as $bill) {
                $this->recordPayment($bill, $paymentAccountId, null, $paidOn, $reference);
            }
        });

        return $bills->count();
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2);
    }
}
