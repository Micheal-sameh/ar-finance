<?php

namespace App\Http\Requests\PurchaseOrders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertToBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('purchase_orders.manage');
    }

    public function rules(): array
    {
        return [
            'bill_number' => [
                'required', 'string', 'max:50',
                Rule::unique('bills', 'bill_number')->where('tenant_id', $this->user()->tenant_id),
            ],
            'bill_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:bill_date'],
            'payable_account_id' => ['required', 'exists:accounts,id'],
        ];
    }
}
