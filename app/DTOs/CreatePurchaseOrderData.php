<?php

namespace App\DTOs;

final readonly class CreatePurchaseOrderData
{
    /**
     * @param  PurchaseOrderLineData[]  $lines
     */
    public function __construct(
        public int $vendorId,
        public string $poNumber,
        public string $orderDate,
        public ?string $expectedDate,
        public array $lines,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            vendorId: (int) $data['vendor_id'],
            poNumber: $data['po_number'],
            orderDate: $data['order_date'],
            expectedDate: $data['expected_date'] ?? null,
            lines: array_map(
                fn (array $line) => PurchaseOrderLineData::fromArray($line),
                $data['lines'],
            ),
        );
    }
}
