<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;

class AssignUserTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tenants.manage');
    }

    public function rules(): array
    {
        return [
            'tenant_ids' => ['nullable', 'array'],
            'tenant_ids.*' => ['integer', 'distinct', 'exists:tenants,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tenant_ids' => 'services',
            'tenant_ids.*' => 'service',
        ];
    }
}
