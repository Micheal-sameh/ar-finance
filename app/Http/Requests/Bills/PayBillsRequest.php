<?php

namespace App\Http\Requests\Bills;

use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;

class PayBillsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bills.manage');
    }

    public function rules(): array
    {
        return [
            'bill_ids' => ['required', 'array', 'min:1', 'max:200'],
            'bill_ids.*' => ['integer', 'distinct', new ExistsInTenant('bills')],
            'payment_account_id' => ['required', new ExistsInTenant('accounts')],
            'paid_on' => ['nullable', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function billIds(): array
    {
        return array_map('intval', $this->validated('bill_ids'));
    }

    public function paymentAccountId(): int
    {
        return (int) $this->validated('payment_account_id');
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
