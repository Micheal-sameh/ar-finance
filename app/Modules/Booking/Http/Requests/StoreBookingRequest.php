<?php

namespace App\Modules\Booking\Http\Requests;

use App\Modules\Booking\DTOs\CreateBookingData;
use App\Modules\Booking\Enums\BookingRecurrence;
use App\Modules\Booking\Models\BookableItem;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

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
            'booking_type_id' => ['nullable', 'exists:booking_types,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'notes' => ['nullable', 'string'],
            'recurrence' => ['nullable', new Enum(BookingRecurrence::class)],
            // A repeating booking needs an end date, otherwise a daily one
            // would never stop.
            'recurrence_until' => ['nullable', 'date', 'after:starts_at'],
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

            if (! in_array($this->input('recurrence', 'none'), [null, '', 'none'], true) && ! $this->filled('recurrence_until')) {
                $validator->errors()->add('recurrence_until', 'Choose when the repeating should stop.');
            }

            if ($item !== null && $item->bookingTypes()->exists()) {
                $typeId = $this->input('booking_type_id');

                if (! $typeId) {
                    $validator->errors()->add('booking_type_id', 'Choose what this booking is for.');
                } elseif (! $item->bookingTypes()->whereKey($typeId)->exists()) {
                    $validator->errors()->add('booking_type_id', 'That booking type is not offered here.');
                }
            }

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
