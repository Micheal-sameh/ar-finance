<?php

namespace App\DTOs;

final readonly class CreateExpenseData
{
    public function __construct(
        public string $description,
        public int $accountId,
        public float $amount,
        public string $date,
        public ?int $vendorId,
        public ?int $costCenterId,
        public int $payableAccountId,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            description: $data['description'],
            accountId: (int) $data['account_id'],
            amount: (float) $data['amount'],
            date: $data['date'],
            vendorId: isset($data['vendor_id']) ? (int) $data['vendor_id'] : null,
            costCenterId: isset($data['cost_center_id']) ? (int) $data['cost_center_id'] : null,
            payableAccountId: (int) $data['payable_account_id'],
        );
    }
}
