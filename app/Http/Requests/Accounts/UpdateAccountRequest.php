<?php

namespace App\Http\Requests\Accounts;

use App\DTOs\CreateAccountData;
use App\Enums\AccountType;
use App\Enums\NormalBalance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('accounts.manage');
    }

    public function rules(): array
    {
        $accountId = $this->route('account')->id;

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('accounts', 'code')->where('tenant_id', $this->user()->tenant_id)->ignore($accountId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(AccountType::class)],
            'normal_balance' => ['nullable', new Enum(NormalBalance::class)],
            'parent_id' => ['nullable', 'exists:accounts,id', Rule::notIn([$accountId])],
            'is_active' => ['boolean'],
        ];
    }

    public function toDto(): CreateAccountData
    {
        return CreateAccountData::fromArray($this->validated());
    }
}
