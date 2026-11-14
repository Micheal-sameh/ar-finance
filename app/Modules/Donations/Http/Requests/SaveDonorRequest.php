<?php

namespace App\Modules\Donations\Http\Requests;

use App\Modules\Donations\DTOs\SaveDonorData;
use Illuminate\Foundation\Http\FormRequest;

class SaveDonorRequest extends FormRequest
{
    /**
     * Whoever can record a donation can add a donor on the spot; changing
     * an existing donor's details needs donations.manage.
     */
    public function authorize(): bool
    {
        return $this->route('donor') === null
            ? $this->user()->can('donations.create')
            : $this->user()->can('donations.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Phone and email are both optional.
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toDto(): SaveDonorData
    {
        return SaveDonorData::fromArray($this->validated());
    }
}
