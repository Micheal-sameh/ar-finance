<?php

namespace App\Modules\Donations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Platform Admin / Portal Manager only (tenants.manage); every other role
 * can only print receipts that carry this text.
 */
class UpdateDonationInstructionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tenants.manage');
    }

    public function rules(): array
    {
        return [
            'instructions' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function instructions(): ?string
    {
        $text = trim((string) $this->input('instructions'));

        return $text === '' ? null : $text;
    }
}
