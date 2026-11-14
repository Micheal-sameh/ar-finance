<?php

namespace App\Http\Requests\Tenants;

use App\Enums\TenantModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateTenantModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tenants.manage');
    }

    public function rules(): array
    {
        return [
            'modules' => ['present', 'array'],
            'modules.*' => ['string', new Enum(TenantModule::class)],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function modules(): array
    {
        return array_values(array_map('strval', $this->validated('modules')));
    }
}
