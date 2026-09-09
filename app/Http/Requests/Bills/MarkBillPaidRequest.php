<?php

namespace App\Http\Requests\Bills;

use Illuminate\Foundation\Http\FormRequest;

class MarkBillPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bills.manage');
    }

    public function rules(): array
    {
        return [
            'payment_account_id' => ['required', 'exists:accounts,id'],
        ];
    }
}
