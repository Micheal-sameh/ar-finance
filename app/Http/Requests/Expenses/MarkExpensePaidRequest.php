<?php

namespace App\Http\Requests\Expenses;

use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;

class MarkExpensePaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.manage');
    }

    public function rules(): array
    {
        return [
            'payment_account_id' => ['required', new ExistsInTenant('accounts')],
        ];
    }
}
