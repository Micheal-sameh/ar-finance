<?php

namespace App\Http\Requests\Users;

use App\Enums\UserStatus;
use App\Models\User;
use App\Rules\GrantableRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

/**
 * Platform Admin / Portal Manager only (tenants.manage): they create
 * accounts and place them on a tenant. Platform-level roles have no tenant.
 */
class StoreUserRequest extends FormRequest
{
    private const PLATFORM_ROLES = ['Platform Admin', 'Portal Manager'];

    public function authorize(): bool
    {
        return $this->user()->can('tenants.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->filled('email') ? strtolower(trim($this->input('email'))) : null,
            'phone' => $this->filled('phone') ? User::normalizePhone($this->input('phone')) : null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isPlatformRole = in_array($this->input('role'), self::PLATFORM_ROLES, true);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:32', 'unique:users,phone'],
            'password' => ['required', 'string', Password::min(8)],
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web'), new GrantableRole],
            'status' => ['required', new Enum(UserStatus::class)],
            'tenant_id' => $isPlatformRole
                ? ['nullable']
                : ['required', 'integer', 'exists:tenants,id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated();

        if (in_array($data['role'], self::PLATFORM_ROLES, true)) {
            $data['tenant_id'] = null;
        }

        return $key === null ? $data : data_get($data, $key, $default);
    }
}
