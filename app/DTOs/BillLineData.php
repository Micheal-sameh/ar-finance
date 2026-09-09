<?php

namespace App\DTOs;

final readonly class BillLineData
{
    public function __construct(
        public string $description,
        public float $quantity,
        public float $unitPrice,
        public int $accountId,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            description: $data['description'],
            quantity: (float) $data['quantity'],
            unitPrice: (float) $data['unit_price'],
            accountId: (int) $data['account_id'],
        );
    }
}
