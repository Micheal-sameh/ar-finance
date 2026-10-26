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

    /**
     * Matched transactions dated on or before $date that no finished
     * reconciliation has claimed yet — what a new reconciliation clears.
     */
    public function clearableUpTo(int $bankAccountId, string $date): Collection;

    public function countUnmatchedUpTo(int $bankAccountId, string $date): int;

    /**
     * @param  array<int, int>  $transactionIds
     */
    public function markReconciled(array $transactionIds, int $reconciliationId): void;

    /**
     * Transactions with no matched journal line yet (auto-match input).
     */
    public function unmatchedForBankAccount(int $bankAccountId): Collection;

    /**
     * Posted lines on the GL account dated on or before $date that the
     * bank had not cleared as of that date: unmatched, matched to a
     * transaction dated after $date, or matched but not reconciled by
     * this ($reconciliationId) or an earlier reconciliation. A null
     * $reconciliationId is a preview, where every matched transaction
     * dated on or before $date counts as cleared.
     */
    public function outstandingJournalLines(int $accountId, string $date, ?int $reconciliationId): Collection;

    /**
     * Net debit-minus-credit of posted lines on the GL account up to $date.
     */
    public function ledgerBalanceUpTo(int $accountId, string $date): float;
}
