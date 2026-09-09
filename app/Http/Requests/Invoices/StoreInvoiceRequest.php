<?php

namespace App\Http\Requests\Invoices;

use App\DTOs\CreateInvoiceData;
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
            'client_id' => ['required', 'exists:clients,id'],
            'invoice_number' => [
                'required', 'string', 'max:50',
                Rule::unique('invoices', 'invoice_number')->where('tenant_id', $this->user()->tenant_id),
            ],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'receivable_account_id' => ['required', 'exists:accounts,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.account_id' => ['required', 'exists:accounts,id'],
        ];
    }

    public function toDto(): CreateInvoiceData
    {
        return CreateInvoiceData::fromArray($this->validated());
    }
}
