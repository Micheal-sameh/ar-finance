<?php

namespace App\Http\Requests\FixedAssets;

use App\DTOs\CreateFixedAssetData;
use App\Enums\DepreciationMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreFixedAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fixed_assets.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'purchase_date' => ['required', 'date'],
            'cost' => ['required', 'numeric', 'min:0.01'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_years' => ['required', 'integer', 'min:1', 'max:100'],
            'depreciation_method' => ['required', new Enum(DepreciationMethod::class)],
            'asset_account_id' => ['required', 'exists:accounts,id'],
            'depreciation_account_id' => ['required', 'exists:accounts,id'],
            'accumulated_depreciation_account_id' => ['required', 'exists:accounts,id'],
        ];
    }

    public function toDto(): CreateFixedAssetData
    {
        return CreateFixedAssetData::fromArray($this->validated());
    }
}
