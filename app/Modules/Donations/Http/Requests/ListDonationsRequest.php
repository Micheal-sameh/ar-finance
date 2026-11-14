<?php

namespace App\Modules\Donations\Http\Requests;

use App\Modules\Donations\Enums\DonationPaymentMethod;
use App\Modules\Donations\Enums\DonationStatus;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ListDonationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('donations.view');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'cost_center_id' => ['nullable', new ExistsInTenant('cost_centers')],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'payment_method' => ['nullable', new Enum(DonationPaymentMethod::class)],
            'status' => ['nullable', new Enum(DonationStatus::class)],
            'created_by' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return [
            'search' => $this->filled('search') ? (string) $this->input('search') : null,
            'from' => $this->filled('from') ? (string) $this->input('from') : null,
            'to' => $this->filled('to') ? (string) $this->input('to') : null,
            'cost_center_id' => $this->filled('cost_center_id') ? (int) $this->input('cost_center_id') : null,
            'currency' => $this->filled('currency') ? strtoupper((string) $this->input('currency')) : null,
            'payment_method' => $this->filled('payment_method') ? (string) $this->input('payment_method') : null,
            'status' => $this->filled('status') ? (string) $this->input('status') : null,
            'created_by' => $this->filled('created_by') ? (int) $this->input('created_by') : null,
        ];
    }
}
