<?php

namespace App\Http\Requests\Expenses;

use App\DTOs\CreateExpenseData;
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
            'account_id' => ['required', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
            'cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'payable_account_id' => ['required', 'exists:accounts,id'],
        ];
    }

    public function toDto(): CreateExpenseData
    {
        return CreateExpenseData::fromArray($this->validated());
    }
}
