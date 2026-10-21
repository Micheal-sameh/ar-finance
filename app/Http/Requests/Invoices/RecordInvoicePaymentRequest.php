<?php

namespace App\Http\Requests\Invoices;

use App\Rules\ExistsInTenant;
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
            'payment_account_id' => ['required', new ExistsInTenant('accounts')],
            // Rate in effect today, if it differs from the rate the
            // invoice was booked at — omit to settle at the booked rate
            // (no FX line). Actual over/under-required-ness of
            // fx_gain_loss_account_id is checked in InvoiceService, since
            // it depends on computing the diff, not just presence.
            'settlement_exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'fx_gain_loss_account_id' => ['nullable', new ExistsInTenant('accounts')],
        ];
    }
}
