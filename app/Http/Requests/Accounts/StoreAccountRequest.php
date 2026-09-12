<?php

namespace App\Http\Requests\Accounts;

use App\DTOs\CreateAccountData;
use App\Enums\AccountType;
use App\Enums\NormalBalance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('accounts.create');
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('accounts', 'code')->where('tenant_id', $this->user()->tenant_id),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $type = AccountType::tryFrom((string) $this->input('type'));

                    if ($type && ! str_starts_with((string) $value, $type->codePrefix())) {
                        $fail("The account code must start with {$type->codePrefix()} for {$type->label()} accounts.");
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(AccountType::class)],
            'normal_balance' => ['nullable', new Enum(NormalBalance::class)],
            'parent_id' => ['nullable', 'exists:accounts,id'],
            'is_active' => ['boolean'],
        ];
    }

    public function toDto(): CreateAccountData
    {
        return CreateAccountData::fromArray($this->validated());
    }
}
