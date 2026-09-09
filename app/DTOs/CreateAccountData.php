<?php

namespace App\DTOs;

use App\Enums\AccountType;
use App\Enums\NormalBalance;

final readonly class CreateAccountData
{
    public function __construct(
        public string $code,
        public string $name,
        public AccountType $type,
        public NormalBalance $normalBalance,
        public ?int $parentId = null,
        public bool $isActive = true,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $type = AccountType::from($data['type']);

        return new self(
            code: $data['code'],
            name: $data['name'],
            type: $type,
            normalBalance: isset($data['normal_balance'])
                ? NormalBalance::from($data['normal_balance'])
                : $type->defaultNormalBalance(),
            parentId: isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }
}
