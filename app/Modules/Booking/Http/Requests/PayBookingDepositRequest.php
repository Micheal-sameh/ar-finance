<?php

namespace App\Modules\Booking\Http\Requests;

use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;

class PayBookingDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bookings.manage');
    }

    public function rules(): array
    {
        return [
            'payment_account_id' => ['required', new ExistsInTenant('accounts')],
            'payment_due_at' => ['required', 'date', 'after:now'],
            // Minimum enforced against the item's deposit_percent in
            // BookingService::payDeposit() — omit to use that minimum.
            'deposit_amount' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}
