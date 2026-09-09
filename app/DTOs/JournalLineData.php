<?php

namespace App\DTOs;

final readonly class JournalLineData
{
    public function __construct(
        public int $accountId,
        public float $debit,
        public float $credit,
        public ?int $costCenterId = null,
        public ?string $description = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            accountId: (int) $data['account_id'],
            debit: (float) ($data['debit'] ?? 0),
            credit: (float) ($data['credit'] ?? 0),
            costCenterId: isset($data['cost_center_id']) ? (int) $data['cost_center_id'] : null,
            description: $data['description'] ?? null,
        );
    }
}
