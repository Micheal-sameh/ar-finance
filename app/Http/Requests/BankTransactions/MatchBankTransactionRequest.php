<?php

namespace App\Http\Requests\BankTransactions;

use Illuminate\Foundation\Http\FormRequest;

class MatchBankTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bank_accounts.manage');
    }

    public function rules(): array
    {
        return [
            'journal_line_id' => ['required', 'exists:journal_lines,id'],
        ];
    }
}
