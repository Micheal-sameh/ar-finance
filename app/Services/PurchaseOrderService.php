<?php

namespace App\Services;

use App\DTOs\CreatePurchaseOrderData;
use App\DTOs\PurchaseOrderLineData;
use App\Models\Bill;
use App\Models\PurchaseOrder;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use RuntimeException;

/**
 * Purchase orders never post to the ledger — they're a pre-commitment
 * document. Only converting one to a Bill (see BillService) posts
 * anything, and that happens once, closing the PO.
 */
class PurchaseOrderService
{
    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $purchaseOrders,
        private readonly BillService $bills,
    ) {
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->purchaseOrders->paginate($filters, $perPage);
    }

    public function find(int $id): ?PurchaseOrder
    {
        return $this->purchaseOrders->find($id);
    }

    public function create(CreatePurchaseOrderData $data): PurchaseOrder
    {
        return $this->purchaseOrders->create(
            attributes: [
                'vendor_id' => $data->vendorId,
                'po_number' => $data->poNumber,
                'order_date' => $data->orderDate,
                'expected_date' => $data->expectedDate,
                'status' => 'draft',
            ],
            lines: array_map(fn (PurchaseOrderLineData $line) => [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice,
                'account_id' => $line->accountId,
            ], $data->lines),
        );
    }

    public function send(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if ($purchaseOrder->status->value !== 'draft') {
            throw new RuntimeException("Purchase order {$purchaseOrder->po_number} has already been sent.");
        }

        return $this->purchaseOrders->updateStatus($purchaseOrder, 'sent');
    }

    public function cancel(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if (! $purchaseOrder->status->isOpenForConversion()) {
            throw new RuntimeException("Purchase order {$purchaseOrder->po_number} can no longer be cancelled.");
        }

        return $this->purchaseOrders->updateStatus($purchaseOrder, 'cancelled');
    }

    /**
     * Creates a draft Bill prefilled from the PO's vendor and lines, and
     * closes the PO. The Bill itself still needs to be approved before
     * anything posts to the ledger.
     */
    public function convertToBill(
        PurchaseOrder $purchaseOrder,
        string $billNumber,
        string $billDate,
        string $dueDate,
        int $payableAccountId,
    ): Bill {
        if (! $purchaseOrder->status->isOpenForConversion()) {
            throw new RuntimeException("Purchase order {$purchaseOrder->po_number} cannot be converted to a bill.");
        }

        $bill = $this->bills->createFromPurchaseOrder($purchaseOrder, $billNumber, $billDate, $dueDate, $payableAccountId);

        $this->purchaseOrders->updateStatus($purchaseOrder, 'closed');

        return $bill;
    }
}
