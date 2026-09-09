<?php

namespace App\Http\Requests\Clients;

use App\DTOs\CreateClientData;
use Illuminate\Foundation\Http\FormRequest;

class SaveClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('clients.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }

    public function toDto(): CreateClientData
    {
        return CreateClientData::fromArray($this->validated());
    }
}
