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
            'code' => ['required', 'string', 'max:20', Rule::unique('accounts', 'code')->where('tenant_id', $this->user()->tenant_id)],
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
