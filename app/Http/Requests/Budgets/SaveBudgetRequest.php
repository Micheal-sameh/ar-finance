<?php

namespace App\Http\Requests\Budgets;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Budget;
use App\Rules\ExistsInTenant;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class SaveBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', Budget::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'lines' => ['present', 'array', 'max:500'],
            'lines.*.account_id' => ['required', new ExistsInTenant('accounts'), $this->mustBeRevenueOrExpense(...)],
            'lines.*.cost_center_id' => ['nullable', new ExistsInTenant('cost_centers')],
            'lines.*.months' => ['required', 'array'],
            'lines.*.months.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.*.months.*.min' => 'Budget amounts cannot be negative.',
            'lines.*.months.*.numeric' => 'Budget amounts must be numbers.',
        ];
    }

    private function mustBeRevenueOrExpense(string $attribute, mixed $value, Closure $fail): void
    {
        $type = Account::query()->whereKey($value)->value('type');

        if (! in_array($type, [AccountType::Revenue, AccountType::Expense], true)) {
            $fail('Only revenue and expense accounts can be budgeted.');
        }
    }
}
