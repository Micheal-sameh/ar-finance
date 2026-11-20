<?php

namespace App\Http\Requests\Tenants;

use App\Enums\TenantModule;
use App\Models\Tenant;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

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
            // Only one tenant can be the treasury (the treasurer's own ledger).
            'is_treasury' => ['boolean', function (string $attribute, mixed $value, Closure $fail) use ($tenant) {
                $otherTreasury = Tenant::query()
                    ->where('is_treasury', true)
                    ->when($tenant, fn ($query) => $query->whereKeyNot($tenant->id))
                    ->exists();

                if ($value && $otherTreasury) {
                    $fail('Another tenant is already the treasury — only one tenant can be.');
                }
            }],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', new Enum(TenantModule::class)],
        ];
    }
}
