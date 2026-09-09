<?php

namespace App\Http\Requests\CostCenters;

use App\DTOs\CreateCostCenterData;
use App\Enums\CostCenterType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class SaveCostCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cost_centers.manage');
    }

    public function rules(): array
    {
        $costCenterId = $this->route('cost_center')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(CostCenterType::class)],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'parent_id' => ['nullable', 'exists:cost_centers,id', Rule::notIn([$costCenterId])],
            'is_active' => ['boolean'],
        ];
    }

    public function toDto(): CreateCostCenterData
    {
        return CreateCostCenterData::fromArray($this->validated());
    }
}
