<?php

namespace App\Services;

use App\DTOs\BankTransactionRowData;
use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalLineData;
use App\Enums\JournalSourceType;
use App\Exceptions\BankReconciliationException;
use App\Exceptions\BankTransactionMatchException;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\JournalLine;
use App\Repositories\Contracts\BankTransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reconciliation itself never posts to the ledger — it only links
 * (matches) an imported bank transaction to a journal line that's
 * already there. The one exception is createAndMatch(), which is really
 * "post a manual entry, then immediately match it" for transactions that
 * haven't been recorded in the books yet (bank fees, interest, ...).
 */
class BankReconciliationService
{
    private const SUGGESTION_WINDOW_DAYS = 7;

    public function __construct(
        private readonly BankTransactionRepositoryInterface $bankTransactions,
        private readonly JournalService $journals,
    ) {}

    public function transactionsFor(BankAccount $bankAccount): Collection
    {
        return $this->bankTransactions->forBankAccount($bankAccount->id);
    }

    public function unmatchedJournalLines(BankAccount $bankAccount): Collection
    {
        return $this->bankTransactions->unmatchedJournalLinesForAccount($bankAccount->account_id);
    }

    /**
     * @param  BankTransactionRowData[]  $rows
     */
    public function importTransactions(BankAccount $bankAccount, array $rows): Collection
    {
        return $this->bankTransactions->createMany($bankAccount->id, array_map(fn (BankTransactionRowData $row) => [
            'tenant_id' => $bankAccount->tenant_id,
            'date' => $row->date,
            'description' => $row->description,
            'amount' => $row->amount,
        ], $rows));
    }

    /**
     * @throws BankTransactionMatchException
     */
    public function match(BankTransaction $bankTransaction, JournalLine $journalLine): BankTransaction
    {
        if ($bankTransaction->isMatched()) {
            throw BankTransactionMatchException::alreadyMatched();
        }

        if ($journalLine->account_id !== $bankTransaction->bankAccount->account_id) {
            throw BankTransactionMatchException::wrongAccount();
        }

        // Deposits (positive) correspond to a debit on the bank's
        // (debit-normal) GL account; withdrawals (negative) to a credit.
        $expectedAmount = (float) $bankTransaction->amount >= 0
            ? (float) $journalLine->debit
            : (float) $journalLine->credit;

        if (abs($expectedAmount - abs((float) $bankTransaction->amount)) > 0.005) {
            throw BankTransactionMatchException::amountMismatch();
        }

        return $this->bankTransactions->matchLine($bankTransaction, $journalLine->id);
    }

    /**
     * @throws BankTransactionMatchException
     */
    public function unmatch(BankTransaction $bankTransaction): BankTransaction
    {
        if ($bankTransaction->isReconciled()) {
            throw BankTransactionMatchException::reconciled();
        }

        return $this->bankTransactions->unmatch($bankTransaction);
    }

    /**
     * For a transaction with no existing GL entry yet: posts a new
     * balanced journal entry against the bank account and the chosen
     * offset account, then matches this transaction to the new bank-side
     * line — one action instead of "post manually, then go match it".
     */
    public function createAndMatch(BankTransaction $bankTransaction, int $offsetAccountId, string $description): BankTransaction
    {
        if ($bankTransaction->isMatched()) {
            throw BankTransactionMatchException::alreadyMatched();
        }

        return DB::transaction(function () use ($bankTransaction, $offsetAccountId, $description) {
            $amount = abs((float) $bankTransaction->amount);
            $bankAccountId = $bankTransaction->bankAccount->account_id;
            $isDeposit = (float) $bankTransaction->amount >= 0;

            $entry = $this->journals->postJournalEntry(new CreateJournalEntryData(
                date: $bankTransaction->date->toDateString(),
                description: $description,
                reference: null,
                sourceType: JournalSourceType::Manual,
                sourceId: null,
                createdBy: auth()->id(),
                lines: $isDeposit
                    ? [
                        new JournalLineData(accountId: $bankAccountId, debit: $amount, credit: 0),
                        new JournalLineData(accountId: $offsetAccountId, debit: 0, credit: $amount),
                    ]
                    : [
                        new JournalLineData(accountId: $offsetAccountId, debit: $amount, credit: 0),
                        new JournalLineData(accountId: $bankAccountId, debit: 0, credit: $amount),
                    ],
            ));

            $bankLine = $entry->lines->firstWhere('account_id', $bankAccountId);

            return $this->bankTransactions->matchLine($bankTransaction, $bankLine->id);
        });
    }

    public function lastReconciliation(BankAccount $bankAccount): ?BankReconciliation
    {
        return BankReconciliation::query()
            ->where('bank_account_id', $bankAccount->id)
            ->orderByDesc('statement_date')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return Collection<int, BankReconciliation>
     */
    public function history(BankAccount $bankAccount): Collection
    {
        return BankReconciliation::query()
            ->with('completedBy:id,name')
            ->where('bank_account_id', $bankAccount->id)
            ->orderByDesc('statement_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Live check for a reconciliation that has not been finished: the
     * opening balance plus everything matched up to the statement date
     * should land exactly on the statement's ending balance.
     *
     * @return array{opening_balance: float, cleared_count: int, cleared_total: float, cleared_balance: float, statement_ending_balance: float, difference: float, unmatched_count: int, is_balanced: bool}
     */
    public function preview(BankAccount $bankAccount, string $statementDate, float $statementEndingBalance, ?float $openingBalance = null): array
    {
        $opening = $this->openingBalanceFor($bankAccount, $openingBalance);
        $cleared = $this->bankTransactions->clearableUpTo($bankAccount->id, $statementDate);
        $clearedTotal = round($cleared->sum(fn (BankTransaction $t) => (float) $t->amount), 2);
        $clearedBalance = round($opening + $clearedTotal, 2);
        $difference = round($statementEndingBalance - $clearedBalance, 2);

        return [
            'opening_balance' => $opening,
            'cleared_count' => $cleared->count(),
            'cleared_total' => $clearedTotal,
            'cleared_balance' => $clearedBalance,
            'statement_ending_balance' => $statementEndingBalance,
            'difference' => $difference,
            'unmatched_count' => $this->bankTransactions->countUnmatchedUpTo($bankAccount->id, $statementDate),
            'is_balanced' => abs($difference) < 0.005,
        ];
    }

    /**
     * Locks every matched transaction up to the statement date into a
     * reconciliation — only allowed when nothing up to that date is left
     * unmatched and the difference is zero.
     *
     * @throws BankReconciliationException
     */
    public function finish(BankAccount $bankAccount, string $statementDate, float $statementEndingBalance, ?float $openingBalance = null): BankReconciliation
    {
        return DB::transaction(function () use ($bankAccount, $statementDate, $statementEndingBalance, $openingBalance) {
            // Serialises concurrent "finish" clicks on the same account.
            BankAccount::query()->whereKey($bankAccount->id)->lockForUpdate()->first();

            $last = $this->lastReconciliation($bankAccount);

            if ($last !== null && $statementDate <= $last->statement_date->toDateString()) {
                throw BankReconciliationException::dateNotAfterPrevious($last->statement_date->toDateString());
            }

            $preview = $this->preview($bankAccount, $statementDate, $statementEndingBalance, $openingBalance);

            if ($preview['unmatched_count'] > 0) {
                throw BankReconciliationException::unmatchedTransactions($preview['unmatched_count']);
            }

            if (! $preview['is_balanced']) {
                throw BankReconciliationException::outOfBalance(number_format($preview['difference'], 2, '.', ''));
            }

            $reconciliation = BankReconciliation::create([
                'tenant_id' => $bankAccount->tenant_id,
                'bank_account_id' => $bankAccount->id,
                'statement_date' => $statementDate,
                'opening_balance' => $preview['opening_balance'],
                'statement_ending_balance' => $statementEndingBalance,
                'completed_by' => auth()->id(),
            ]);

            $this->bankTransactions->markReconciled(
                $this->bankTransactions->clearableUpTo($bankAccount->id, $statementDate)->modelKeys(),
                $reconciliation->id,
            );

            return $reconciliation;
        });
    }

    /**
     * Classic bank reconciliation statement: the bank's balance adjusted
     * for items the books have but the bank has not cleared yet, compared
     * with the ledger balance. Pass the saved reconciliation to see a
     * finished one exactly as of its statement date.
     *
     * @return array{statement_date: string, statement_ending_balance: float, outstanding_deposits: array<int, array<string, mixed>>, outstanding_withdrawals: array<int, array<string, mixed>>, outstanding_net: float, adjusted_bank_balance: float, ledger_balance: float, difference: float}
     */
    public function report(BankAccount $bankAccount, string $statementDate, float $statementEndingBalance, ?BankReconciliation $reconciliation = null): array
    {
        $outstanding = $this->bankTransactions
            ->outstandingJournalLines($bankAccount->account_id, $statementDate, $reconciliation?->id)
            ->map(fn (JournalLine $line) => [
                'id' => $line->id,
                'date' => $line->journalEntry->date->toDateString(),
                'description' => $line->description ?? $line->journalEntry->description,
                'reference' => $line->journalEntry->reference,
                'amount' => round((float) $line->debit - (float) $line->credit, 2),
            ]);

        $net = round($outstanding->sum('amount'), 2);
        $adjusted = round($statementEndingBalance + $net, 2);
        $ledger = $this->bankTransactions->ledgerBalanceUpTo($bankAccount->account_id, $statementDate);

        return [
            'statement_date' => $statementDate,
            'statement_ending_balance' => $statementEndingBalance,
            'outstanding_deposits' => $outstanding->filter(fn ($item) => $item['amount'] >= 0)->values()->all(),
            'outstanding_withdrawals' => $outstanding->filter(fn ($item) => $item['amount'] < 0)->values()->all(),
            'outstanding_net' => $net,
            'adjusted_bank_balance' => $adjusted,
            'ledger_balance' => $ledger,
            'difference' => round($adjusted - $ledger, 2),
        ];
    }

    /**
     * Best one-to-one pairing of unmatched statement lines with unmatched
     * ledger lines of the same signed amount. A pair is only suggested
     * when dated within SUGGESTION_WINDOW_DAYS of each other; closer dates
     * and more similar descriptions rank higher.
     *
     * @return array<int, array{bank_transaction_id: int, journal_line_id: int, score: int}>
     */
    public function suggestMatches(BankAccount $bankAccount): array
    {
        $lines = $this->unmatchedJournalLines($bankAccount);
        $pairs = [];

        foreach ($this->bankTransactions->unmatchedForBankAccount($bankAccount->id) as $transaction) {
            foreach ($lines as $line) {
                $signed = (float) $line->debit - (float) $line->credit;

                if (abs($signed - (float) $transaction->amount) > 0.005) {
                    continue;
                }

                $days = abs($transaction->date->diffInDays($line->journalEntry->date));

                if ($days > self::SUGGESTION_WINDOW_DAYS) {
                    continue;
                }

                similar_text(
                    mb_strtolower($transaction->description),
                    mb_strtolower((string) ($line->description ?? $line->journalEntry->description)),
                    $similarity,
                );

                $pairs[] = [
                    'bank_transaction_id' => $transaction->id,
                    'journal_line_id' => $line->id,
                    'score' => (int) round((1 - $days / (self::SUGGESTION_WINDOW_DAYS + 1)) * 50 + $similarity / 2),
                ];
            }
        }

        usort($pairs, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        $usedTransactions = [];
        $usedLines = [];
        $suggestions = [];

        foreach ($pairs as $pair) {
            if (isset($usedTransactions[$pair['bank_transaction_id']]) || isset($usedLines[$pair['journal_line_id']])) {
                continue;
            }

            $usedTransactions[$pair['bank_transaction_id']] = true;
            $usedLines[$pair['journal_line_id']] = true;
            $suggestions[] = $pair;
        }

        return $suggestions;
    }

    /**
     * Applies every current suggestion; returns how many were matched.
     */
    public function applySuggestions(BankAccount $bankAccount): int
    {
        $matched = 0;

        DB::transaction(function () use ($bankAccount, &$matched) {
            foreach ($this->suggestMatches($bankAccount) as $suggestion) {
                $this->match(
                    BankTransaction::query()->findOrFail($suggestion['bank_transaction_id']),
                    JournalLine::query()->findOrFail($suggestion['journal_line_id']),
                );
                $matched++;
            }
        });

        return $matched;
    }

    private function openingBalanceFor(BankAccount $bankAccount, ?float $enteredOpening): float
    {
        $last = $this->lastReconciliation($bankAccount);

        return $last !== null ? (float) $last->statement_ending_balance : round($enteredOpening ?? 0, 2);
    }
}
