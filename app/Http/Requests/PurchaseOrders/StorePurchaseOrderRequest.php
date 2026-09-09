<?php

namespace App\Http\Requests\PurchaseOrders;

use App\DTOs\CreatePurchaseOrderData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('purchase_orders.create');
    }

    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'exists:vendors,id'],
            'po_number' => [
                'required', 'string', 'max:50',
                Rule::unique('purchase_orders', 'po_number')->where('tenant_id', $this->user()->tenant_id),
            ],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.account_id' => ['required', 'exists:accounts,id'],
        ];
    }

    public function toDto(): CreatePurchaseOrderData
    {
        return CreatePurchaseOrderData::fromArray($this->validated());
    }
}
