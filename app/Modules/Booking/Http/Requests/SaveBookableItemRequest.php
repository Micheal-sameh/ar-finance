<?php

namespace App\Modules\Booking\Http\Requests;

use App\Modules\Booking\DTOs\CreateBookableItemData;
use App\Modules\Booking\Enums\BookingRateType;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SaveBookableItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bookable_items.manage');
    }

    /**
     * Only finance/admin choose accounts and cost centers; for booking
     * staff those inputs are never validated, so they never reach the DTO.
     */
    public function canSetAccounting(): bool
    {
        return $this->user()->can('bookable_items.accounting');
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_exclusive' => ['boolean'],
            'is_active' => ['boolean'],
            'booking_types' => ['required', 'array', 'min:1'],
            'booking_types.*.booking_type_id' => ['required', 'distinct', new ExistsInTenant('booking_types')],
            'booking_types.*.rate_type' => ['required', new Enum(BookingRateType::class)],
            'booking_types.*.price' => ['required', 'numeric', 'min:0'],
        ];

        if (! $this->canSetAccounting()) {
            return $rules;
        }

        return $rules + [
            // Optional: left empty, the GL mapping's booking revenue
            // account is used (see BookableItemService).
            'account_id' => ['nullable', new ExistsInTenant('accounts')],
            'cost_center_id' => ['nullable', new ExistsInTenant('cost_centers')],
            'booking_types.*.account_id' => ['nullable', new ExistsInTenant('accounts')],
            'booking_types.*.cost_center_id' => ['nullable', new ExistsInTenant('cost_centers')],
        ];
    }

    public function toDto(): CreateBookableItemData
    {
        return CreateBookableItemData::fromArray($this->validated(), $this->canSetAccounting());
    }
}
