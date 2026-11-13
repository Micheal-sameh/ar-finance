<?php

namespace App\Modules\Booking\Http\Requests;

use App\Modules\Booking\DTOs\CreateBookingData;
use App\Modules\Booking\Enums\BookingDiscountType;
use App\Modules\Booking\Enums\BookingPaymentMethod;
use App\Modules\Booking\Enums\BookingRecurrence;
use App\Modules\Booking\Models\BookableItem;
use App\Rules\ExistsInTenant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'bookable_item_id' => ['required', new ExistsInTenant('bookable_items')],
            'booking_type_id' => ['nullable', new ExistsInTenant('booking_types')],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'notes' => ['nullable', 'string'],
            'payment_method' => ['required', new Enum(BookingPaymentMethod::class)],
            'recurrence' => ['nullable', new Enum(BookingRecurrence::class)],
            // A repeating booking needs an end date, otherwise a daily one
            // would never stop.
            'recurrence_until' => ['nullable', 'date', 'after:starts_at'],
            // A fixed discount is checked against the price in BookingService.
            'discount_type' => ['nullable', 'required_with:discount_value', new Enum(BookingDiscountType::class)],
            'discount_value' => [
                'nullable',
                'required_with:discount_type',
                'numeric',
                'min:0.01',
                Rule::when($this->input('discount_type') === BookingDiscountType::Percent->value, ['max:100']),
            ],
        ];
    }

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
        });
    }

    public function toDto(): CreateBookingData
    {
        return CreateBookingData::fromArray($this->validated());
    }
}
