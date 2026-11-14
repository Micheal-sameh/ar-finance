<?php

namespace App\Modules\Donations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Platform Admin / Portal Manager only (tenants.manage).
 */
class RejectDonationCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tenants.manage');
    }

    public function rules(): array
    {
        return [];
    }
}
