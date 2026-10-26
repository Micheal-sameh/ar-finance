<?php

namespace App\Http\Requests\Bills;

use App\Enums\BillStatus;
use App\Models\Bill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListBillsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Bill::class);
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(BillStatus::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array{status?: string, from?: string, to?: string, search?: string}
     */
    public function filters(): array
    {
        return $this->safe()->only(['status', 'from', 'to', 'search']);
    }
}
