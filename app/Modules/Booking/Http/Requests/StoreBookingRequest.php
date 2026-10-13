<?php

namespace App\Modules\Booking\Http\Requests;

use App\Modules\Booking\DTOs\CreateBookingData;
use App\Modules\Booking\Models\BookableItem;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bookings.create');
    }

    public function rules(): array
    {
        return [
            'bookable_item_id' => ['required', 'exists:bookable_items,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'notes' => ['nullable', 'string'],
            'payment_due_at' => ['nullable', 'date', 'after:now'],
            // The admin's deposit_percent sets the minimum — the person
            // booking can offer more, enforced against the priced booking
            // in BookingService (the price isn't known until then).
            'deposit_amount' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }

    /**
     * The deposit percentage — and the account it's collected into — are
     * set by the admin on the bookable item, not chosen here. But once an
     * item has a deposit, paying it is mandatory at booking time rather
     * than left as an optional follow-up step, so the due date is required.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $item = BookableItem::find($this->input('bookable_item_id'));

            if ($item?->acceptsDeposits() && ! $this->filled('payment_due_at')) {
                $validator->errors()->add('payment_due_at', 'Set when the remaining balance is due.');
            }
        });
    }

    public function toDto(): CreateBookingData
    {
        return CreateBookingData::fromArray($this->validated());
    }
}
