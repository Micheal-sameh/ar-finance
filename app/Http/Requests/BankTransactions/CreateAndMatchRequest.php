<?php

namespace App\Http\Requests\BankTransactions;

use Illuminate\Foundation\Http\FormRequest;

class CreateAndMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bank_accounts.manage');
    }

    public function rules(): array
    {
        return [
            'offset_account_id' => ['required', 'exists:accounts,id'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }
}
