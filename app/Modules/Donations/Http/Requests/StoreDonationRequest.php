<?php

namespace App\Modules\Donations\Http\Requests;

use App\Modules\Donations\DTOs\CreateDonationData;
use App\Modules\Donations\Enums\DonationPaymentMethod;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreDonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('donations.create');
    }

    public function rules(): array
    {
        return [
            // Left empty for an anonymous donation.
            'donor_id' => ['nullable', new ExistsInTenant('donors')],
            'cost_center_id' => ['required', new ExistsInTenant('cost_centers')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
            // Blank means the tenant's base currency.
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            // Only needed for a foreign currency when no rate is on file.
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'donated_at' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', new Enum(DonationPaymentMethod::class)],
            // The cash receipt, POS transaction or bank transfer number.
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toDto(): CreateDonationData
    {
        return CreateDonationData::fromArray($this->validated());
    }
}
