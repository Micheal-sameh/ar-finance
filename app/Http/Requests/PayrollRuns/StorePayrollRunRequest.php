<?php

namespace App\Http\Requests\PayrollRuns;

use App\DTOs\CreatePayrollRunData;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.create');
    }

    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'pay_date' => ['required', 'date'],
            'expense_account_id' => ['required', new ExistsInTenant('accounts')],
            'payable_account_id' => ['required', new ExistsInTenant('accounts')],
            'deductions_payable_account_id' => [
                Rule::requiredIf(fn () => collect($this->input('payslips', []))->sum('deductions') > 0),
                'nullable', new ExistsInTenant('accounts'),
            ],
            'payslips' => ['required', 'array', 'min:1'],
            'payslips.*.employee_id' => ['required', new ExistsInTenant('employees')],
            'payslips.*.gross_pay' => ['required', 'numeric', 'min:0.01'],
            'payslips.*.deductions' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function toDto(): CreatePayrollRunData
    {
        return CreatePayrollRunData::fromArray($this->validated());
    }
}
