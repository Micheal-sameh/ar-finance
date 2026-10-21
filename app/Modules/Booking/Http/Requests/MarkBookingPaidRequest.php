<?php

namespace App\Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkBookingPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bookings.manage');
    }

    public function rules(): array
    {
        return [
            'payment_account_id' => ['required', 'exists:accounts,id'],
            // Omit to collect the full remaining balance (current
            // behaviour); a smaller amount records a partial payment and
            // leaves the booking confirmed — see BookingService::markPaid().
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}
