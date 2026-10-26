<?php

namespace App\Http\Controllers;

use App\Exceptions\BankReconciliationException;
use App\Http\Controllers\Concerns\ExportsExcel;
use App\Http\Requests\BankReconciliations\FinishBankReconciliationRequest;
use App\Http\Requests\BankReconciliations\PreviewBankReconciliationRequest;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Services\BankReconciliationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankReconciliationController extends Controller
{
    use ExportsExcel;

    public function __construct(
        private readonly BankReconciliationService $reconciliation,
    ) {}

    public function store(FinishBankReconciliationRequest $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('update', $bankAccount);

        try {
            $reconciliation = $this->reconciliation->finish(
                $bankAccount,
                $request->statementDate(),
                $request->statementEndingBalance(),
                $request->openingBalance(),
                $request->adjustmentAccountId(),
            );
        } catch (BankReconciliationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('bank-reconciliations.show', $reconciliation)->with('success', 'Reconciliation finished.');
    }

    public function destroy(BankReconciliation $bankReconciliation): RedirectResponse
    {
        $bankAccount = $bankReconciliation->bankAccount;
        $this->authorize('update', $bankAccount);

        try {
            $this->reconciliation->reopen($bankReconciliation);
        } catch (BankReconciliationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('bank-accounts.show', $bankAccount)->with('success', 'Reconciliation reopened.');
    }

    public function show(BankReconciliation $bankReconciliation): Response
    {
        $bankAccount = $bankReconciliation->bankAccount->load('account');
        $this->authorize('view', $bankAccount);

        return Inertia::render('Banking/BankAccounts/Reconciliation', [
            'bankAccount' => $bankAccount,
            'reconciliation' => $bankReconciliation->load('completedBy:id,name'),
            'report' => $this->reconciliation->reportFor($bankReconciliation),
        ]);
    }

    /**
     * The same statement for a reconciliation that has not been finished
     * yet, driven by the date and balance typed on the account page.
     */
    public function preview(PreviewBankReconciliationRequest $request, BankAccount $bankAccount): Response
    {
        $this->authorize('view', $bankAccount);

        return Inertia::render('Banking/BankAccounts/Reconciliation', [
            'bankAccount' => $bankAccount->load('account'),
            'reconciliation' => null,
            'report' => $this->reconciliation->report($bankAccount, $request->statementDate(), $request->statementEndingBalance()),
        ]);
    }

    public function export(BankReconciliation $bankReconciliation): StreamedResponse
    {
        $this->authorize('view', $bankReconciliation->bankAccount);

        return $this->exportXlsx(
            "bank-reconciliation-{$bankReconciliation->statement_date->toDateString()}.xlsx",
            ['Item', 'Date', 'Description', 'Amount'],
            $this->reconciliation->exportRows($this->reconciliation->reportFor($bankReconciliation)),
        );
    }
}
