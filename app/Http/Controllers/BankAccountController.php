<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankAccounts\SaveBankAccountRequest;
use App\Http\Requests\BankTransactions\ImportBankTransactionsRequest;
use App\Models\BankAccount;
use App\Services\BankAccountService;
use App\Services\BankReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class BankAccountController extends Controller
{
    public function __construct(
        private readonly BankAccountService $bankAccounts,
        private readonly BankReconciliationService $reconciliation,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BankAccount::class);

        return Inertia::render('Banking/BankAccounts/Index', [
            'bankAccounts' => $this->bankAccounts->paginate($request->only(['search'])),
            'filters' => $request->only(['search']),
        ]);
    }

    public function show(BankAccount $bankAccount): Response
    {
        $this->authorize('view', $bankAccount);

        return Inertia::render('Banking/BankAccounts/Show', [
            'bankAccount' => $this->bankAccounts->find($bankAccount->id),
            'transactions' => $this->reconciliation->transactionsFor($bankAccount),
            'unmatchedLines' => $this->reconciliation->unmatchedJournalLines($bankAccount),
        ]);
    }

    public function store(SaveBankAccountRequest $request): RedirectResponse
    {
        $this->bankAccounts->create($request->toDto());

        return redirect()->route('bank-accounts.index')->with('success', 'Bank account added.');
    }

    public function update(SaveBankAccountRequest $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('update', $bankAccount);

        $this->bankAccounts->update($bankAccount, $request->toDto());

        return redirect()->route('bank-accounts.index')->with('success', 'Bank account updated.');
    }

    public function destroy(BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('delete', $bankAccount);

        try {
            $this->bankAccounts->delete($bankAccount);
        } catch (RuntimeException $e) {
            return redirect()->route('bank-accounts.index')->with('error', $e->getMessage());
        }

        return redirect()->route('bank-accounts.index')->with('success', 'Bank account deleted.');
    }

    public function import(ImportBankTransactionsRequest $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('update', $bankAccount);

        $imported = $this->reconciliation->importTransactions($bankAccount, $request->rows());

        return back()->with('success', "Imported {$imported->count()} transaction(s).");
    }
}
