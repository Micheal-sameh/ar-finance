<?php

namespace App\Http\Requests\PayrollRuns;

use Illuminate\Foundation\Http\FormRequest;

class MarkPayrollPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.manage');
    }

    public function rules(): array
    {
        return [
            'payment_account_id' => ['required', 'exists:accounts,id'],
        ];
    }
}
