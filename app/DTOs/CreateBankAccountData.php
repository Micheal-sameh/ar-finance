<?php

namespace App\DTOs;

final readonly class CreateBankAccountData
{
    public function __construct(
        public string $name,
        public int $accountId,
        public ?string $bankName,
        public ?string $accountNumber,
        public string $currency,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            accountId: (int) $data['account_id'],
            bankName: $data['bank_name'] ?? null,
            accountNumber: $data['account_number'] ?? null,
            currency: $data['currency'] ?? 'USD',
        );
    }
}
