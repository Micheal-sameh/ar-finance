<?php

namespace App\Services;

use App\DTOs\CreateAccountData;
use App\DTOs\CreateJournalEntryData;
use App\Enums\AccountType;
use App\Enums\JournalSourceType;
use App\Enums\NormalBalance;
use App\Exceptions\AccountImportException;
use App\Exceptions\AccountInUseException;
use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
    ) {}

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
     * Imports a batch of accounts parsed from an uploaded spreadsheet.
     * Rows are validated in "parent before child" order (by the
     * chart-of-accounts nesting depth of their code) against both the
     * existing chart and the rest of the batch, so a row can reference a
     * parent defined earlier in the same file. Nothing is created unless
     * every row is valid — a single bad row fails the whole import rather
     * than leaving a partial chart of accounts.
     *
     * @param  array<int, array{row: int, code: string, name: string, type: AccountType, normal_balance: ?NormalBalance, parent_code: ?string, is_active: bool, opening_balance: ?float}>  $rows
     *
     * @throws AccountImportException
     */
    public function import(array $rows, int $createdBy): int
    {
        return DB::transaction(function () use ($rows, $createdBy) {
            $sorted = $rows;
            usort(
                $sorted,
                fn (array $a, array $b) => [strlen(Account::significantCodePrefix($a['code'])), $a['code']]
                    <=> [strlen(Account::significantCodePrefix($b['code'])), $b['code']],
            );

            $knownTypes = [];
            $usedCodes = [];
            $usedNames = [];

            foreach ($this->accounts->filtered() as $account) {
                $knownTypes[$account->code] = $account->type;
                $usedCodes[$account->code] = true;
                $usedNames[$account->type->value.'|'.mb_strtolower($account->name)] = true;
            }

            $errors = [];
            $valid = [];

            foreach ($sorted as $row) {
                $rowErrors = $this->validateImportRow($row, $knownTypes, $usedCodes, $usedNames);

                if ($rowErrors !== []) {
                    foreach ($rowErrors as $message) {
                        $errors[] = "Row {$row['row']}: {$message}";
                    }

                    continue;
                }

                $knownTypes[$row['code']] = $row['type'];
                $usedCodes[$row['code']] = true;
                $usedNames[$row['type']->value.'|'.mb_strtolower($row['name'])] = true;
                $valid[] = $row;
            }

            if ($errors !== []) {
                throw new AccountImportException($errors);
            }

            $codeToId = [];

            foreach ($valid as $row) {
                $parentId = $row['parent_code'] !== null
                    ? ($codeToId[$row['parent_code']] ?? $this->accounts->findByCode($row['parent_code'])?->id)
                    : null;

                $account = $this->create(CreateAccountData::fromArray([
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'type' => $row['type']->value,
                    'normal_balance' => $row['normal_balance']?->value,
                    'parent_id' => $parentId,
                    'is_active' => $row['is_active'],
                    'opening_balance' => $row['opening_balance'],
                ]), $createdBy);

                $codeToId[$account->code] = $account->id;
            }

            return count($valid);
        });
    }

    /**
     * @param  array<string, AccountType>  $knownTypes
     * @param  array<string, true>  $usedCodes
     * @param  array<string, true>  $usedNames
     * @return string[]
     */
    private function validateImportRow(array $row, array $knownTypes, array $usedCodes, array $usedNames): array
    {
        $errors = [];

        if (! str_starts_with($row['code'], $row['type']->codePrefix())) {
            $errors[] = "the account code must start with {$row['type']->codePrefix()} for {$row['type']->label()} accounts.";
        }

        if (isset($usedCodes[$row['code']])) {
            $errors[] = "code {$row['code']} is already in use.";
        }

        $nameKey = $row['type']->value.'|'.mb_strtolower($row['name']);

        if (isset($usedNames[$nameKey])) {
            $errors[] = "the name \"{$row['name']}\" is already used by another {$row['type']->label()} account.";
        }

        if ($row['parent_code'] !== null) {
            $parentType = $knownTypes[$row['parent_code']] ?? null;

            if (! $parentType) {
                $errors[] = "parent code {$row['parent_code']} was not found (it must exist already or appear earlier in the file).";
            } elseif ($parentType !== $row['type']) {
                $errors[] = "the parent account must be a {$row['type']->label()} account.";
            } elseif (! Account::codeNestsUnder($row['code'], $row['parent_code'])) {
                $errors[] = "the account code must be a direct child of {$row['parent_code']}: same length as the parent code, varying only the next digit, with every digit after it zero.";
            }
        }

        return $errors;
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
