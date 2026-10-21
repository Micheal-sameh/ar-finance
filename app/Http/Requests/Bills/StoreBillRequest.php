<?php

namespace App\Http\Requests\Bills;

use App\DTOs\CreateBillData;
use App\Rules\ExistsInTenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bills.create');
    }

    public function rules(): array
    {
        return [
            'vendor_id' => ['required', new ExistsInTenant('vendors')],
            'purchase_order_id' => ['nullable', new ExistsInTenant('purchase_orders')],
            'bill_number' => [
                'required', 'string', 'max:50',
                Rule::unique('bills', 'bill_number')->where('tenant_id', app(TenantContext::class)->id()),
            ],
            'bill_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:bill_date'],
            'payable_account_id' => ['required', new ExistsInTenant('accounts')],
            'tax_receivable_account_id' => [
                Rule::requiredIf(fn () => collect($this->input('lines', []))->sum('tax_rate') > 0),
                'nullable', new ExistsInTenant('accounts'),
            ],
            'cost_center_id' => ['nullable', new ExistsInTenant('cost_centers')],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.account_id' => ['required', new ExistsInTenant('accounts')],
        ];
    }

    public function toDto(): CreateBillData
    {
        return CreateBillData::fromArray($this->validated());
    }
}
