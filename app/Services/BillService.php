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
use Illuminate\Pagination\LengthAwarePaginator;
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
    ) {
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->bills->paginate($filters, $perPage);
    }

    public function find(int $id): ?Bill
    {
        return $this->bills->find($id);
    }

    /**
     * Drafts don't touch the ledger — only approve() does.
     */
    public function create(CreateBillData $data): Bill
    {
        return $this->bills->create(
            attributes: [
                'tenant_id' => auth()->user()->tenant_id,
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

    public function markPaid(Bill $bill, int $paymentAccountId): Bill
    {
        if ($bill->status->value !== 'approved') {
            throw new RuntimeException("Bill {$bill->bill_number} is not awaiting payment.");
        }

        return DB::transaction(function () use ($bill, $paymentAccountId) {
            $this->journals->postJournalEntry(new CreateJournalEntryData(
                date: now()->toDateString(),
                description: "Payment for bill {$bill->bill_number}",
                reference: $bill->bill_number,
                sourceType: JournalSourceType::Expense,
                sourceId: $bill->id,
                createdBy: auth()->id(),
                lines: [
                    new JournalLineData(accountId: $bill->payable_account_id, debit: $bill->total(), credit: 0),
                    new JournalLineData(accountId: $paymentAccountId, debit: 0, credit: $bill->total()),
                ],
            ));

            return $this->bills->updateStatus($bill, 'paid', now());
        });
    }
}
