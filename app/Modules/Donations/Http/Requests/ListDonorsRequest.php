<?php

namespace App\Modules\Donations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListDonorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('donations.view');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array{search: string|null}
     */
    public function filters(): array
    {
        return ['search' => $this->filled('search') ? (string) $this->input('search') : null];
    }
}
