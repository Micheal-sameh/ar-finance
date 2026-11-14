<?php

namespace App\Modules\Donations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowDonationReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('donations.view');
    }

    public function rules(): array
    {
        return [
            'inline' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Whether to open the PDF in the browser (for printing) instead of
     * downloading it.
     */
    public function inline(): bool
    {
        return $this->boolean('inline');
    }
}
