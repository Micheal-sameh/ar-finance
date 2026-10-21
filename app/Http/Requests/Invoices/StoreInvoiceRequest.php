<?php

namespace App\Http\Requests\Invoices;

use App\DTOs\CreateInvoiceData;
use App\Rules\ExistsInTenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('invoices.create');
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', new ExistsInTenant('clients')],
            'invoice_number' => [
                'required', 'string', 'max:50',
                Rule::unique('invoices', 'invoice_number')->where('tenant_id', app(TenantContext::class)->id()),
            ],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'receivable_account_id' => ['required', new ExistsInTenant('accounts')],
            'tax_payable_account_id' => [
                Rule::requiredIf(fn () => collect($this->input('lines', []))->sum('tax_rate') > 0),
                'nullable', new ExistsInTenant('accounts'),
            ],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.account_id' => ['required', new ExistsInTenant('accounts')],
        ];
    }

    public function toDto(): CreateInvoiceData
    {
        return CreateInvoiceData::fromArray($this->validated());
    }
}
