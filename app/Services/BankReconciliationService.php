<?php

namespace App\Services;

use App\DTOs\BankTransactionRowData;
use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalLineData;
use App\Enums\JournalSourceType;
use App\Exceptions\BankTransactionMatchException;
use App\Models\BankAccount;
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
    public function __construct(
        private readonly BankTransactionRepositoryInterface $bankTransactions,
        private readonly JournalService $journals,
    ) {
    }

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

    public function unmatch(BankTransaction $bankTransaction): BankTransaction
    {
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
}
