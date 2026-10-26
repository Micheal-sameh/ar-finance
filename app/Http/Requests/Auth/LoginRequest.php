<?php

namespace App\Http\Requests\Auth;

use App\Rules\Turnstile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
            'cf-turnstile-response' => [Rule::requiredIf(fn () => filled(config('services.turnstile.site_key'))), 'nullable', 'string', new Turnstile($this->ip())],
        ];
    }
}
