<?php

namespace App\Http\Requests\GlMapping;

use App\Models\GlMapping;
use App\Rules\ExistsInTenant;
use App\Services\PaymentAccountService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePaymentAccountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', GlMapping::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'accounts' => ['present', 'array', 'max:50'],
            'accounts.*.method' => ['required', Rule::in(PaymentAccountService::METHODS)],
            'accounts.*.account_id' => ['required', new ExistsInTenant('accounts')],
            'accounts.*.label' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<int, array{method: string, account_id: int, label: string|null}>
     */
    public function accounts(): array
    {
        return array_values(array_map(fn (array $row) => [
            'method' => (string) $row['method'],
            'account_id' => (int) $row['account_id'],
            'label' => isset($row['label']) && trim((string) $row['label']) !== '' ? trim((string) $row['label']) : null,
        ], $this->validated('accounts')));
    }
}
