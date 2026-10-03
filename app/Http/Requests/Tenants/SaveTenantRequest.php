<?php

namespace App\Http\Requests\Tenants;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tenants.manage');
    }

    public function rules(): array
    {
        $tenant = $this->route('tenant');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('tenants', 'slug')->ignore($tenant)],
            'base_currency' => ['required', 'string', 'size:3'],
            'is_active' => ['boolean'],
        ];
    }
}
