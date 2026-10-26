<?php

namespace App\Http\Requests\Bills;

use App\Rules\ExistsInTenant;
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
            'payment_account_id' => ['required', new ExistsInTenant('accounts')],
            // Left empty, the whole remaining balance is paid.
            'amount' => ['nullable', 'numeric', 'gt:0', 'max:9999999999999999'],
            'paid_on' => ['nullable', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function paymentAccountId(): int
    {
        return (int) $this->validated('payment_account_id');
    }

    public function amount(): ?float
    {
        return $this->filled('amount') ? (float) $this->validated('amount') : null;
    }

    public function paidOn(): ?string
    {
        return $this->filled('paid_on') ? $this->validated('paid_on') : null;
    }

    public function reference(): ?string
    {
        return $this->filled('reference') ? $this->validated('reference') : null;
    }
}
