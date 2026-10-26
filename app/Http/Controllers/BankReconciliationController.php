<?php

namespace App\Http\Controllers;

use App\Exceptions\BankReconciliationException;
use App\Http\Controllers\Concerns\ExportsExcel;
use App\Http\Requests\BankReconciliations\FinishBankReconciliationRequest;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Services\BankReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                $request->validated('statement_date'),
                (float) $request->validated('statement_ending_balance'),
                $request->filled('opening_balance') ? (float) $request->validated('opening_balance') : null,
            );
        } catch (BankReconciliationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('bank-reconciliations.show', $reconciliation)->with('success', 'Reconciliation finished.');
    }

    public function show(BankReconciliation $bankReconciliation): Response
    {
        $bankAccount = $bankReconciliation->bankAccount()->with('account')->firstOrFail();
        $this->authorize('view', $bankAccount);

        return Inertia::render('Banking/BankAccounts/Reconciliation', [
            'bankAccount' => $bankAccount,
            'reconciliation' => $bankReconciliation->load('completedBy:id,name'),
            'report' => $this->reportFor($bankReconciliation, $bankAccount),
        ]);
    }

    /**
     * The same statement for a reconciliation that has not been finished
     * yet, driven by the date and balance typed on the account page.
     */
    public function preview(Request $request, BankAccount $bankAccount): Response
    {
        $this->authorize('view', $bankAccount);

        $validated = $request->validate([
            'statement_date' => ['required', 'date'],
            'statement_ending_balance' => ['required', 'numeric'],
        ]);

        return Inertia::render('Banking/BankAccounts/Reconciliation', [
            'bankAccount' => $bankAccount->load('account'),
            'reconciliation' => null,
            'report' => $this->reconciliation->report(
                $bankAccount,
                $validated['statement_date'],
                (float) $validated['statement_ending_balance'],
            ),
        ]);
    }

    public function export(BankReconciliation $bankReconciliation): StreamedResponse
    {
        $bankAccount = $bankReconciliation->bankAccount()->with('account')->firstOrFail();
        $this->authorize('view', $bankAccount);

        $report = $this->reportFor($bankReconciliation, $bankAccount);

        $rows = collect([
            ['Statement ending balance', null, null, $report['statement_ending_balance']],
        ])
            ->concat(collect($report['outstanding_deposits'])->map(fn ($i) => ['Outstanding deposit', $i['date'], $i['description'], $i['amount']]))
            ->concat(collect($report['outstanding_withdrawals'])->map(fn ($i) => ['Outstanding withdrawal', $i['date'], $i['description'], $i['amount']]))
            ->concat([
                ['Adjusted bank balance', null, null, $report['adjusted_bank_balance']],
                ['Ledger balance', null, null, $report['ledger_balance']],
                ['Difference', null, null, $report['difference']],
            ]);

        return $this->exportXlsx(
            "bank-reconciliation-{$bankReconciliation->statement_date->toDateString()}.xlsx",
            ['Item', 'Date', 'Description', 'Amount'],
            $rows,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function reportFor(BankReconciliation $bankReconciliation, BankAccount $bankAccount): array
    {
        return $this->reconciliation->report(
            $bankAccount,
            $bankReconciliation->statement_date->toDateString(),
            (float) $bankReconciliation->statement_ending_balance,
            $bankReconciliation,
        );
    }
}
