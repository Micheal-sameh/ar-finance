<?php

namespace App\Http\Requests\Accounts;

use App\DTOs\CreateAccountData;
use App\Enums\AccountType;
use App\Enums\NormalBalance;
use App\Models\Account;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('accounts.manage');
    }

    public function rules(): array
    {
        $accountId = $this->route('account')->id;

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('accounts', 'code')->where('tenant_id', app(TenantContext::class)->id())->ignore($accountId),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $type = AccountType::tryFrom((string) $this->input('type'));

                    if ($type && ! str_starts_with((string) $value, $type->codePrefix())) {
                        $fail("The account code must start with {$type->codePrefix()} for {$type->label()} accounts.");
                    }
                },
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('accounts', 'name')
                    ->where('tenant_id', app(TenantContext::class)->id())
                    ->where('type', $this->input('type'))
                    ->ignore($accountId),
            ],
            'type' => ['required', new Enum(AccountType::class)],
            'normal_balance' => ['nullable', new Enum(NormalBalance::class)],
            'currency' => ['nullable', 'string', 'size:3'],
            'parent_id' => [
                'nullable',
                'exists:accounts,id',
                Rule::notIn([$accountId]),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value) {
                        return;
                    }

                    $type = AccountType::tryFrom((string) $this->input('type'));
                    $parent = Account::find($value);

                    if ($type && $parent && $parent->type !== $type) {
                        $fail("The parent account must be a {$type->label()} account.");
                    }

                    if ($parent && $parent->journalLines()->exists()) {
                        $fail('The parent account must not already have a balance or postings — parent accounts are labels only.');
                    }
                },
            ],
            'is_active' => ['boolean'],
        ];
    }

    public function toDto(): CreateAccountData
    {
        return CreateAccountData::fromArray([
            ...$this->validated(),
            'currency' => $this->validated('currency') ?? $this->route('account')->currency,
        ]);
    }
}
