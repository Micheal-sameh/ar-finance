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
 * AR side of the same pattern.
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
                'vendor_id' => $data->vendorId,
                'purchase_order_id' => $data->purchaseOrderId,
                'bill_number' => $data->billNumber,
                'bill_date' => $data->billDate,
                'due_date' => $data->dueDate,
                'payable_account_id' => $data->payableAccountId,
                'cost_center_id' => $data->costCenterId,
                'status' => 'draft',
            ],
            lines: array_map(fn (BillLineData $line) => [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice,
                'account_id' => $line->accountId,
            ], $data->lines),
        );
    }

    /**
     * Prefills a draft bill from a purchase order's vendor and lines —
     * the PO itself never posts to the ledger, only the bill created
     * from it does, once approved.
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
            costCenterId: null,
            lines: $purchaseOrder->lines->map(fn ($line) => new BillLineData(
                description: $line->description,
                quantity: (float) $line->quantity,
                unitPrice: (float) $line->unit_price,
                accountId: $line->account_id,
            ))->all(),
        ));
    }

    /**
     * Recognizes the expense: debits each line's account, credits the
     * bill's payable control account for the total.
     */
    public function approve(Bill $bill): Bill
    {
        if (! $bill->status->isEditable()) {
            throw new RuntimeException("Bill {$bill->bill_number} has already been approved.");
        }

        return DB::transaction(function () use ($bill) {
            $lines = [
                ...$bill->lines->map(fn ($line) => new JournalLineData(
                    accountId: $line->account_id,
                    debit: $line->subtotal(),
                    credit: 0,
                    costCenterId: $bill->cost_center_id,
                    description: $line->description,
                ))->all(),
                new JournalLineData(
                    accountId: $bill->payable_account_id,
                    debit: 0,
                    credit: $bill->total(),
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
