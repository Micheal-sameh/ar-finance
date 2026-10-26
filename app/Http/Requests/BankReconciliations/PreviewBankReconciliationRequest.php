<?php

namespace App\Http\Requests\BankReconciliations;

use Illuminate\Foundation\Http\FormRequest;

class PreviewBankReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bank_accounts.view');
    }

    public function rules(): array
    {
        return [
            'statement_date' => ['required', 'date'],
            'statement_ending_balance' => ['required', 'numeric'],
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
}
