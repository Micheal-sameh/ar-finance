<?php

namespace App\Http\Requests\Journals;

use App\DTOs\CreateJournalEntryData;
use App\Enums\JournalSourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'lines.*.cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('lines', []) as $index => $line) {
                $hasAmount = (float) ($line['debit'] ?? 0) > 0 || (float) ($line['credit'] ?? 0) > 0;

                if ($hasAmount && empty($line['cost_center_id'])) {
                    $validator->errors()->add("lines.{$index}.cost_center_id", 'The cost center field is required.');
                }
            }
        });
    }

    public function toDto(): CreateJournalEntryData
    {
        $data = $this->validated();
        $data['source_type'] = JournalSourceType::Manual->value;
        $data['created_by'] = $this->user()->id;

        return CreateJournalEntryData::fromArray($data);
    }
}
