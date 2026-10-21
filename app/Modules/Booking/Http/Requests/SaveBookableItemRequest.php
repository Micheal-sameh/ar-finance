<?php

namespace App\Modules\Booking\Http\Requests;

use App\Modules\Booking\DTOs\CreateBookableItemData;
use App\Modules\Booking\Enums\BookingRateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SaveBookableItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bookable_items.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_exclusive' => ['boolean'],
            'deposit_percent' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'account_id' => ['required', 'exists:accounts,id'],
            // Only needed once deposits are accepted — the deposit posts
            // here until the booking is fully paid (see BookingService).
            'deferred_revenue_account_id' => ['required_with:deposit_percent', 'nullable', 'exists:accounts,id'],
            // Which asset account a deposit lands in — set here by the
            // admin so it's never chosen per booking.
            'deposit_payment_account_id' => ['required_with:deposit_percent', 'nullable', 'exists:accounts,id'],
            'cost_center_id' => ['required', 'exists:cost_centers,id'],
            'is_active' => ['boolean'],
            'booking_types' => ['required', 'array', 'min:1'],
            'booking_types.*.booking_type_id' => ['required', 'distinct', 'exists:booking_types,id'],
            'booking_types.*.rate_type' => ['required', new Enum(BookingRateType::class)],
            'booking_types.*.price' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function toDto(): CreateBookableItemData
    {
        return CreateBookableItemData::fromArray($this->validated());
    }
}
