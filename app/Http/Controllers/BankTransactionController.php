<?php

namespace App\Http\Controllers;

use App\Exceptions\BankTransactionMatchException;
use App\Http\Requests\BankTransactions\CreateAndMatchRequest;
use App\Http\Requests\BankTransactions\MatchBankTransactionRequest;
use App\Models\BankTransaction;
use App\Models\JournalLine;
use App\Services\BankReconciliationService;
use Illuminate\Http\RedirectResponse;

class BankTransactionController extends Controller
{
    public function __construct(
        private readonly BankReconciliationService $reconciliation,
    ) {
    }

    public function match(MatchBankTransactionRequest $request, BankTransaction $bankTransaction): RedirectResponse
    {
        $this->authorize('update', $bankTransaction->bankAccount);

        $journalLine = JournalLine::findOrFail($request->validated('journal_line_id'));

        try {
            $this->reconciliation->match($bankTransaction, $journalLine);
        } catch (BankTransactionMatchException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transaction matched.');
    }

    public function unmatch(BankTransaction $bankTransaction): RedirectResponse
    {
        $this->authorize('update', $bankTransaction->bankAccount);

        $this->reconciliation->unmatch($bankTransaction);

        return back()->with('success', 'Transaction unmatched.');
    }

    public function createAndMatch(CreateAndMatchRequest $request, BankTransaction $bankTransaction): RedirectResponse
    {
        $this->authorize('update', $bankTransaction->bankAccount);

        try {
            $this->reconciliation->createAndMatch(
                $bankTransaction,
                (int) $request->validated('offset_account_id'),
                $request->validated('description'),
            );
        } catch (BankTransactionMatchException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Journal entry posted and matched.');
    }
}
