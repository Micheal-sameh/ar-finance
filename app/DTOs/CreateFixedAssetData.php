<?php

namespace App\DTOs;

use App\Enums\DepreciationMethod;

final readonly class CreateFixedAssetData
{
    public function __construct(
        public string $name,
        public string $purchaseDate,
        public float $cost,
        public float $salvageValue,
        public int $usefulLifeYears,
        public DepreciationMethod $depreciationMethod,
        public int $assetAccountId,
        public int $depreciationAccountId,
        public int $accumulatedDepreciationAccountId,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            purchaseDate: $data['purchase_date'],
            cost: (float) $data['cost'],
            salvageValue: (float) ($data['salvage_value'] ?? 0),
            usefulLifeYears: (int) $data['useful_life_years'],
            depreciationMethod: DepreciationMethod::from($data['depreciation_method'] ?? 'straight_line'),
            assetAccountId: (int) $data['asset_account_id'],
            depreciationAccountId: (int) $data['depreciation_account_id'],
            accumulatedDepreciationAccountId: (int) $data['accumulated_depreciation_account_id'],
        );
    }
}
