<?php

namespace App\Http\Requests\Employees;

use App\DTOs\CreateEmployeeData;
use Illuminate\Foundation\Http\FormRequest;

class SaveEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('employees.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'salary' => ['required', 'numeric', 'min:0.01'],
            'hire_date' => ['required', 'date'],
            'is_active' => ['boolean'],
        ];
    }

    public function toDto(): CreateEmployeeData
    {
        return CreateEmployeeData::fromArray($this->validated());
    }
}
