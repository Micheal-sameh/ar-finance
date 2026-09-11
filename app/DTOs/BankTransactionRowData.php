<?php

namespace App\DTOs;

final readonly class BankTransactionRowData
{
    public function __construct(
        public string $date,
        public string $description,
        public float $amount,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            date: $data['date'],
            description: $data['description'],
            amount: (float) $data['amount'],
        );
    }
}
