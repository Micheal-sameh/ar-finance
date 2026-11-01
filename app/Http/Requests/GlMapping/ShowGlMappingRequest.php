<?php

namespace App\Http\Requests\GlMapping;

use App\Models\GlMapping;
use Illuminate\Foundation\Http\FormRequest;

class ShowGlMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', GlMapping::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
