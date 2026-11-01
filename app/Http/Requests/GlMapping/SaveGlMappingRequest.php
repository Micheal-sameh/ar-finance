<?php

namespace App\Http\Requests\GlMapping;

use App\Enums\GlMappingRole;
use App\Models\GlMapping;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;

class SaveGlMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', GlMapping::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = ['mappings' => ['required', 'array']];

        foreach (GlMappingRole::cases() as $role) {
            $rules["mappings.{$role->value}"] = ['nullable', new ExistsInTenant('accounts')];
        }

        return $rules;
    }

    /**
     * Account id by role value; only the roles the request actually sent.
     *
     * @return array<string, int|null>
     */
    public function mappings(): array
    {
        $mappings = [];
        foreach (GlMappingRole::cases() as $role) {
            if ($this->has("mappings.{$role->value}")) {
                $value = $this->input("mappings.{$role->value}");
                $mappings[$role->value] = $value === null || $value === '' ? null : (int) $value;
            }
        }

        return $mappings;
    }
}
