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
}
