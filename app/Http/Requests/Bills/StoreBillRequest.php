<?php

namespace App\Http\Requests\Bills;

use App\DTOs\CreateBillData;
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
            'vendor_id' => ['required', 'exists:vendors,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'bill_number' => [
                'required', 'string', 'max:50',
                Rule::unique('bills', 'bill_number')->where('tenant_id', $this->user()->tenant_id),
            ],
            'bill_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:bill_date'],
            'payable_account_id' => ['required', 'exists:accounts,id'],
            'cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.account_id' => ['required', 'exists:accounts,id'],
        ];
    }

    public function toDto(): CreateBillData
    {
        return CreateBillData::fromArray($this->validated());
    }
}
