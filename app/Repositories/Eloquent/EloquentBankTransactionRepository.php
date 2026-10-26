<?php

namespace App\Repositories\Eloquent;

use App\Models\BankTransaction;
use App\Models\JournalLine;
use App\Repositories\Contracts\BankTransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentBankTransactionRepository implements BankTransactionRepositoryInterface
{
    public function forBankAccount(int $bankAccountId): Collection
    {
        return BankTransaction::query()
            ->with('matchedJournalLine.journalEntry')
            ->where('bank_account_id', $bankAccountId)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();
    }

    public function find(int $id): ?BankTransaction
    {
        return BankTransaction::query()->with(['bankAccount.account', 'matchedJournalLine.journalEntry'])->find($id);
    }

    public function createMany(int $bankAccountId, array $rows): Collection
    {
        $transactions = collect($rows)->map(fn (array $row) => BankTransaction::create([
            ...$row,
            'bank_account_id' => $bankAccountId,
        ]));

        return new Collection($transactions);
    }

    public function matchLine(BankTransaction $bankTransaction, int $journalLineId): BankTransaction
    {
        $bankTransaction->update(['matched_journal_line_id' => $journalLineId]);

        return $bankTransaction;
    }

    public function unmatch(BankTransaction $bankTransaction): BankTransaction
    {
        $bankTransaction->update(['matched_journal_line_id' => null]);

        return $bankTransaction;
    }

    public function unmatchedJournalLinesForAccount(int $accountId): Collection
    {
        return JournalLine::query()
            ->with('journalEntry')
            ->where('account_id', $accountId)
            ->whereHas('journalEntry', fn ($query) => $query->whereNotNull('posted_at'))
            ->whereDoesntHave('matchedByBankTransaction')
            ->get();
    }

    public function clearableUpTo(int $bankAccountId, string $date): Collection
    {
        return BankTransaction::query()
            ->where('bank_account_id', $bankAccountId)
            ->whereNotNull('matched_journal_line_id')
            ->whereNull('bank_reconciliation_id')
            ->whereDate('date', '<=', $date)
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    public function countUnmatchedUpTo(int $bankAccountId, string $date): int
    {
        return BankTransaction::query()
            ->where('bank_account_id', $bankAccountId)
            ->whereNull('matched_journal_line_id')
            ->whereDate('date', '<=', $date)
            ->count();
    }

    public function markReconciled(array $transactionIds, int $reconciliationId): void
    {
        BankTransaction::query()
            ->whereIn('id', $transactionIds)
            ->update(['bank_reconciliation_id' => $reconciliationId]);
    }

    public function unmatchedForBankAccount(int $bankAccountId): Collection
    {
        return BankTransaction::query()
            ->where('bank_account_id', $bankAccountId)
            ->whereNull('matched_journal_line_id')
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    public function outstandingJournalLines(int $accountId, string $date, ?int $reconciliationId): Collection
    {
        return JournalLine::query()
            ->with('journalEntry')
            ->where('account_id', $accountId)
            ->whereHas('journalEntry', fn ($query) => $query->whereNotNull('posted_at')->whereDate('date', '<=', $date))
            ->where(function ($query) use ($date, $reconciliationId) {
                $query->whereDoesntHave('matchedByBankTransaction')
                    ->orWhereHas('matchedByBankTransaction', function ($transaction) use ($date, $reconciliationId) {
                        $transaction->where(function ($cleared) use ($date, $reconciliationId) {
                            $cleared->whereDate('date', '>', $date);

                            // Preview: a match dated on/before the date is cleared by this very run.
                            if ($reconciliationId !== null) {
                                $cleared->orWhereNull('bank_reconciliation_id')
                                    ->orWhere('bank_reconciliation_id', '>', $reconciliationId);
                            }
                        });
                    });
            })
            ->get()
            ->sortBy(fn (JournalLine $line) => $line->journalEntry->date)
            ->values();
    }

    public function ledgerBalanceUpTo(int $accountId, string $date): float
    {
        $totals = JournalLine::query()
            ->where('account_id', $accountId)
            ->whereHas('journalEntry', fn ($query) => $query->whereNotNull('posted_at')->whereDate('date', '<=', $date))
            ->selectRaw('COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')
            ->first();

        return round((float) $totals->debit - (float) $totals->credit, 2);
    }
}
