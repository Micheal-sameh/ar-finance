<?php

namespace App\Modules\Donations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The approvals queue — Platform Admin / Portal Manager only.
 */
class ListCancelRequestsRequest extends FormRequest
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
