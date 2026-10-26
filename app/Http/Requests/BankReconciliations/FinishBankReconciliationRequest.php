<?php

namespace App\Http\Requests\BankReconciliations;

use Illuminate\Foundation\Http\FormRequest;

class FinishBankReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bank_accounts.manage');
    }

    public function rules(): array
    {
        return [
            'statement_date' => ['required', 'date'],
            'statement_ending_balance' => ['required', 'numeric', 'between:-9999999999999999,9999999999999999'],
            // Only used for the very first reconciliation of an account;
            // afterwards the previous ending balance is carried forward.
            'opening_balance' => ['nullable', 'numeric', 'between:-9999999999999999,9999999999999999'],
        ];
    }
}
