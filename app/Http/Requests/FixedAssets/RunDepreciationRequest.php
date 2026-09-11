<?php

namespace App\Http\Requests\FixedAssets;

use Illuminate\Foundation\Http\FormRequest;

class RunDepreciationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fixed_assets.manage');
    }

    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
        ];
    }
}
