<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Name, email and phone of an existing user. Email and phone are the login
 * identifiers, so each stays unique and at least one must remain.
 */
class UpdateUserDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.manage');
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
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($user)],
        ];
    }

    public function name(): string
    {
        return $this->validated('name');
    }

    public function email(): ?string
    {
        return $this->validated('email');
    }

    public function phone(): ?string
    {
        return $this->validated('phone');
    }
}
