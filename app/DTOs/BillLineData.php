<?php

namespace App\DTOs;

final readonly class BillLineData
{
    public function __construct(
        public string $description,
        public float $quantity,
        public float $unitPrice,
        public float $taxRate,
        public int $accountId,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            description: $data['description'],
            quantity: (float) $data['quantity'],
            unitPrice: (float) $data['unit_price'],
            taxRate: (float) ($data['tax_rate'] ?? 0),
            accountId: (int) $data['account_id'],
        );
    }
}
