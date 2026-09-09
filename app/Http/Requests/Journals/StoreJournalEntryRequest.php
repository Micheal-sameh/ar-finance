<?php

namespace App\Http\Requests\Journals;

use App\DTOs\CreateJournalEntryData;
use App\Enums\JournalSourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('journals.create');
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'exists:accounts,id'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toDto(): CreateJournalEntryData
    {
        $data = $this->validated();
        $data['source_type'] = JournalSourceType::Manual->value;
        $data['created_by'] = $this->user()->id;

        return CreateJournalEntryData::fromArray($data);
    }
}
