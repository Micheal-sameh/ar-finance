<?php

namespace App\Http\Requests\Bills;

use Illuminate\Foundation\Http\FormRequest;

class ApproveBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bills.manage');
    }

    public function rules(): array
    {
        return [];
    }
}
