<?php

namespace App\Repositories\Contracts;

use App\Models\BankTransaction;
use Illuminate\Database\Eloquent\Collection;

interface BankTransactionRepositoryInterface
{
    /**
     * All transactions for a bank account, newest first.
     */
    public function forBankAccount(int $bankAccountId): Collection;

    public function find(int $id): ?BankTransaction;

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function createMany(int $bankAccountId, array $rows): Collection;

    public function matchLine(BankTransaction $bankTransaction, int $journalLineId): BankTransaction;

    public function unmatch(BankTransaction $bankTransaction): BankTransaction;

    /**
     * Posted journal lines on the given GL account that no bank
     * transaction has claimed yet — candidates for matching.
     */
    public function unmatchedJournalLinesForAccount(int $accountId): Collection;
}
