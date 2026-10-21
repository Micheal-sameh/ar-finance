<?php

namespace App\Http\Requests\Expenses;

use App\DTOs\CreateExpenseData;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.create');
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'account_id' => ['required', new ExistsInTenant('accounts')],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'vendor_id' => ['nullable', new ExistsInTenant('vendors')],
            'cost_center_id' => ['nullable', new ExistsInTenant('cost_centers')],
            'payable_account_id' => ['required', new ExistsInTenant('accounts')],
        ];
    }

    public function toDto(): CreateExpenseData
    {
        return CreateExpenseData::fromArray($this->validated());
    }
}
