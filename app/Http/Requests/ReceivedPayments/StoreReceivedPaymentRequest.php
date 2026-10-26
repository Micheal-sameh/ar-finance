<?php

namespace App\Http\Requests\ReceivedPayments;

use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;

class StoreReceivedPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('invoices.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'deposit_account_id' => ['required', new ExistsInTenant('accounts')],
            'credit_account_id' => ['required', 'different:deposit_account_id', new ExistsInTenant('accounts')],
            'client_id' => ['nullable', new ExistsInTenant('clients')],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
