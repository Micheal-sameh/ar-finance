<?php

namespace App\DTOs;

use App\Enums\CostCenterType;

final readonly class CreateCostCenterData
{
    public function __construct(
        public string $name,
        public CostCenterType $type,
        public ?float $budget,
        public ?int $parentId,
        public bool $isActive = true,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            type: CostCenterType::from($data['type']),
            budget: isset($data['budget']) && $data['budget'] !== '' ? (float) $data['budget'] : null,
            parentId: isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }
}
