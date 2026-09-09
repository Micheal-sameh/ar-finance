<?php

namespace App\Http\Requests\Vendors;

use App\DTOs\CreateVendorData;
use Illuminate\Foundation\Http\FormRequest;

class SaveVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('vendors.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function toDto(): CreateVendorData
    {
        return CreateVendorData::fromArray($this->validated());
    }
}
