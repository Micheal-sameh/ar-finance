<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;

class RecordInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('invoices.manage');
    }

    public function rules(): array
    {
        return [
            'payment_account_id' => ['required', 'exists:accounts,id'],
        ];
    }
}
