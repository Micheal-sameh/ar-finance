<?php

namespace App\Http\Requests\Users;

use App\Rules\GrantableRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.manage');
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web'), new GrantableRole],
        ];
    }
}
