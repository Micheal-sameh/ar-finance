<?php

namespace App\Http\Requests\BankAccounts;

use App\DTOs\CreateBankAccountData;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;

class SaveBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bank_accounts.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'account_id' => ['required', new ExistsInTenant('accounts')],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }

    public function toDto(): CreateBankAccountData
    {
        return CreateBankAccountData::fromArray($this->validated());
    }
}
