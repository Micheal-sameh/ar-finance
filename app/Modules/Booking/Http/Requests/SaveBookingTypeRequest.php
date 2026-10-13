<?php

namespace App\Modules\Booking\Http\Requests;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBookingTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bookable_items.manage');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('booking_types', 'name')
                    ->where('tenant_id', app(TenantContext::class)->id())
                    ->ignore($this->route('bookingType')),
            ],
        ];
    }
}
