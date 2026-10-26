<?php

namespace App\Http\Requests\BankReconciliations;

use App\Rules\ExistsInTenant;
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
            'statement_date' => ['required', 'date', 'before_or_equal:today'],
            'statement_ending_balance' => ['required', 'numeric', 'between:-9999999999999999,9999999999999999'],
            // Only used for the very first reconciliation of an account;
            // afterwards the previous ending balance is carried forward.
            'opening_balance' => ['nullable', 'numeric', 'between:-9999999999999999,9999999999999999'],
            // Optional write-off account for a leftover difference.
            'adjustment_account_id' => ['nullable', new ExistsInTenant('accounts')],
        ];
    }

    public function statementDate(): string
    {
        return $this->validated('statement_date');
    }

    public function statementEndingBalance(): float
    {
        return (float) $this->validated('statement_ending_balance');
    }

    public function openingBalance(): ?float
    {
        return $this->filled('opening_balance') ? (float) $this->validated('opening_balance') : null;
    }

    public function adjustmentAccountId(): ?int
    {
        return $this->filled('adjustment_account_id') ? (int) $this->validated('adjustment_account_id') : null;
    }
}
