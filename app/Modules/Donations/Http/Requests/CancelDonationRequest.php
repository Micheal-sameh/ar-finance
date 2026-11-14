<?php

namespace App\Modules\Donations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelDonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('donations.manage');
    }

    public function rules(): array
    {
        return [];
    }
}
