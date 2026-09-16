<?php

namespace App\Services;

use App\DTOs\CreateAccountData;
use App\DTOs\CreateJournalEntryData;
use App\Enums\AccountType;
use App\Enums\JournalSourceType;
use App\Exceptions\AccountInUseException;
use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class AccountService
{
    /**
     * Code for the auto-provisioned contra account that balances any
     * account's opening-balance journal entry.
     */
    private const OPENING_BALANCE_EQUITY_CODE = '3900';

    public function __construct(
        private readonly AccountRepositoryInterface $accounts,
        private readonly JournalService $journals,
    ) {
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->accounts->paginate($filters, $perPage);
    }

    public function all(): Collection
    {
        return $this->accounts->all();
    }

    public function filtered(array $filters = []): Collection
    {
        return $this->accounts->filtered($filters);
    }

    public function find(int $id): ?Account
    {
        return $this->accounts->find($id);
    }

    public function create(CreateAccountData $data, int $createdBy): Account
    {
        $account = $this->accounts->create([
            'code' => $data->code,
            'name' => $data->name,
            'type' => $data->type,
            'normal_balance' => $data->normalBalance,
            'parent_id' => $data->parentId,
            'is_active' => $data->isActive,
        ]);

        if ($data->openingBalance !== null && round($data->openingBalance, 2) !== 0.0) {
            $this->postOpeningBalance($account, $data->openingBalance, $createdBy);
        }

        return $account;
    }

    /**
     * Books the account's starting balance against the auto-provisioned
     * "Opening Balance Equity" account so the new account never enters the
     * ledger with an unbalanced entry.
     */
    private function postOpeningBalance(Account $account, float $amount, int $createdBy): void
    {
        $equity = $this->openingBalanceEquityAccount();
        $absAmount = round(abs($amount), 2);
        $debitsNewAccount = $account->type->isDebitNormal() ? $amount > 0 : $amount < 0;

        $this->journals->postJournalEntry(CreateJournalEntryData::fromArray([
            'date' => now()->toDateString(),
            'description' => "Opening balance — {$account->code} {$account->name}",
            'source_type' => JournalSourceType::OpeningBalance->value,
            'source_id' => $account->id,
            'created_by' => $createdBy,
            'lines' => [
                [
                    'account_id' => $account->id,
                    'debit' => $debitsNewAccount ? $absAmount : 0,
                    'credit' => $debitsNewAccount ? 0 : $absAmount,
                ],
                [
                    'account_id' => $equity->id,
                    'debit' => $debitsNewAccount ? 0 : $absAmount,
                    'credit' => $debitsNewAccount ? $absAmount : 0,
                ],
            ],
        ]));
    }

    private function openingBalanceEquityAccount(): Account
    {
        return $this->accounts->findByCode(self::OPENING_BALANCE_EQUITY_CODE)
            ?? $this->accounts->create([
                'code' => self::OPENING_BALANCE_EQUITY_CODE,
                'name' => 'Opening Balance Equity',
                'type' => AccountType::Equity,
                'normal_balance' => AccountType::Equity->defaultNormalBalance(),
                'parent_id' => null,
                'is_active' => true,
                'is_deletable' => false,
            ]);
    }

    public function update(Account $account, CreateAccountData $data): Account
    {
        return $this->accounts->update($account, [
            'code' => $data->code,
            'name' => $data->name,
            'type' => $data->type,
            'normal_balance' => $data->normalBalance,
            'parent_id' => $data->parentId,
            'is_active' => $data->isActive,
        ]);
    }

    /**
     * @throws AccountInUseException
     */
    public function delete(Account $account): void
    {
        if (! $account->is_deletable) {
            throw AccountInUseException::notDeletable($account->code);
        }

        if ($this->accounts->hasJournalLines($account)) {
            throw AccountInUseException::hasJournalLines($account->code);
        }

        if ($this->accounts->hasChildren($account)) {
            throw AccountInUseException::hasChildren($account->code);
        }

        $this->accounts->delete($account);
    }
}
