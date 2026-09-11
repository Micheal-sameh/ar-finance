<?php

namespace App\DTOs;

final readonly class CreateBillData
{
    /**
     * @param  BillLineData[]  $lines
     */
    public function __construct(
        public int $vendorId,
        public ?int $purchaseOrderId,
        public string $billNumber,
        public string $billDate,
        public string $dueDate,
        public int $payableAccountId,
        public ?int $taxReceivableAccountId,
        public ?int $costCenterId,
        public array $lines,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            vendorId: (int) $data['vendor_id'],
            purchaseOrderId: isset($data['purchase_order_id']) ? (int) $data['purchase_order_id'] : null,
            billNumber: $data['bill_number'],
            billDate: $data['bill_date'],
            dueDate: $data['due_date'],
            payableAccountId: (int) $data['payable_account_id'],
            taxReceivableAccountId: isset($data['tax_receivable_account_id']) ? (int) $data['tax_receivable_account_id'] : null,
            costCenterId: isset($data['cost_center_id']) ? (int) $data['cost_center_id'] : null,
            lines: array_map(
                fn (array $line) => BillLineData::fromArray($line),
                $data['lines'],
            ),
        );
    }
}
