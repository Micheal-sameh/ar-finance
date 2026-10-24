<?php

namespace App\Http\Requests\Journals;

use App\DTOs\CreateJournalEntryData;
use App\Enums\AccountType;
use App\Enums\CostCenterType;
use App\Enums\JournalSourceType;
use App\Models\Account;
use App\Models\CostCenter;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
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
            'lines.*.account_id' => ['required', new ExistsInTenant('accounts')],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
            'lines.*.cost_center_id' => ['nullable', new ExistsInTenant('cost_centers')],
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

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $lines = (array) $this->input('lines', []);
            $accountTypes = $this->accountTypes($lines);
            $centerTypes = CostCenter::query()
                ->whereIn('id', collect($lines)->pluck('cost_center_id')->filter())
                ->pluck('type', 'id');

            foreach ($lines as $index => $line) {
                $requiredCenterType = $this->requiredCenterType($accountTypes[$line['account_id']] ?? null);

                if ($requiredCenterType !== null && ($centerTypes[$line['cost_center_id']] ?? null) !== $requiredCenterType) {
                    $validator->errors()->add(
                        "lines.{$index}.cost_center_id",
                        $requiredCenterType === CostCenterType::Profit
                            ? 'Revenue accounts require a profit center.'
                            : 'Cost accounts require a cost center.',
                    );
                }
            }
        });
    }

    public function toDto(): CreateJournalEntryData
    {
        $data = $this->validated();
        $data['source_type'] = JournalSourceType::Manual->value;
        $data['created_by'] = $this->user()->id;
        $data['lines'] = $this->forceSideByAccountType($data['lines']);

        return CreateJournalEntryData::fromArray($data);
    }

    /**
     * Revenue lines are always posted as a debit and cost (expense) lines
     * as a credit, whichever column the amount was entered in.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function forceSideByAccountType(array $lines): array
    {
        $accountTypes = $this->accountTypes($lines);

        return array_map(function (array $line) use ($accountTypes) {
            $amount = (float) $line['debit'] + (float) $line['credit'];

            return match ($accountTypes[$line['account_id']] ?? null) {
                AccountType::Revenue => [...$line, 'debit' => $amount, 'credit' => 0],
                AccountType::Expense => [...$line, 'debit' => 0, 'credit' => $amount],
                default => $line,
            };
        }, $lines);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return Collection<int|string, AccountType>
     */
    private function accountTypes(array $lines): Collection
    {
        return Account::query()
            ->whereIn('id', collect($lines)->pluck('account_id')->filter())
            ->pluck('type', 'id');
    }

    private function requiredCenterType(?AccountType $type): ?CostCenterType
    {
        return match ($type) {
            AccountType::Revenue => CostCenterType::Profit,
            AccountType::Expense => CostCenterType::Cost,
            default => null,
        };
    }
}
