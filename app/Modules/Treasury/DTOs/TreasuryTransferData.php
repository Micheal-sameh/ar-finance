<?php

namespace App\Modules\Treasury\DTOs;

use App\Modules\Treasury\Enums\TreasuryTransferType;

final readonly class TreasuryTransferData
{
    public function __construct(
        public TreasuryTransferType $type,
        public float $amount,
        public string $date,
        public ?int $tenantId = null,
        public ?int $tenantAccountId = null,
        public ?int $treasuryAccountId = null,
        public ?int $counterAccountId = null,
        public ?string $reference = null,
        public ?string $notes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(TreasuryTransferType $type, array $data): self
    {
        $optionalInt = fn (string $key): ?int => isset($data[$key]) && $data[$key] !== '' ? (int) $data[$key] : null;
        $optionalString = fn (string $key): ?string => isset($data[$key]) && trim((string) $data[$key]) !== '' ? trim((string) $data[$key]) : null;

        return new self(
            type: $type,
            amount: round((float) $data['amount'], 2),
            date: (string) $data['transfer_date'],
            tenantId: $optionalInt('tenant_id'),
            tenantAccountId: $optionalInt('tenant_account_id'),
            treasuryAccountId: $optionalInt('treasury_account_id'),
            counterAccountId: $optionalInt('counter_account_id'),
            reference: $optionalString('reference'),
            notes: $optionalString('notes'),
        );
    }
}
