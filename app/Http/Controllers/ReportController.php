<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\AccountService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly AccountService $accounts,
    ) {
    }

    public function trialBalance(Request $request): Response
    {
        $this->authorize('viewAny', Account::class);

        $from = $request->string('from')->value() ?: null;
        $to = $request->string('to')->value() ?: null;

        return Inertia::render('Accounting/Reports/TrialBalance', [
            'report' => $this->reports->trialBalance($from, $to),
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function generalLedger(Request $request): Response
    {
        $this->authorize('viewAny', Account::class);

        $accountId = $request->integer('account_id') ?: null;
        $from = $request->string('from')->value() ?: null;
        $to = $request->string('to')->value() ?: null;

        $account = $accountId ? $this->accounts->find($accountId) : null;

        return Inertia::render('Accounting/Reports/GeneralLedger', [
            'accounts' => $this->accounts->all(),
            'account' => $account,
            'ledger' => $account ? $this->reports->generalLedger($account, $from, $to) : null,
            'filters' => ['account_id' => $accountId, 'from' => $from, 'to' => $to],
        ]);
    }

    public function profitAndLoss(Request $request): Response
    {
        $this->authorize('viewAny', Account::class);

        $from = $request->string('from')->value() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->value() ?: now()->toDateString();
        $compareFrom = $request->string('compare_from')->value() ?: null;
        $compareTo = $request->string('compare_to')->value() ?: null;

        return Inertia::render('Accounting/Reports/ProfitAndLoss', [
            'report' => $this->reports->profitAndLoss($from, $to, $compareFrom, $compareTo),
            'filters' => ['from' => $from, 'to' => $to, 'compare_from' => $compareFrom, 'compare_to' => $compareTo],
        ]);
    }

    public function balanceSheet(Request $request): Response
    {
        $this->authorize('viewAny', Account::class);

        $asOf = $request->string('as_of')->value() ?: now()->toDateString();

        return Inertia::render('Accounting/Reports/BalanceSheet', [
            'report' => $this->reports->balanceSheet($asOf),
            'filters' => ['as_of' => $asOf],
        ]);
    }

    public function vatReturn(Request $request): Response
    {
        $this->authorize('viewAny', Account::class);

        $from = $request->string('from')->value() ?: now()->startOfQuarter()->toDateString();
        $to = $request->string('to')->value() ?: now()->toDateString();

        return Inertia::render('Accounting/Reports/VatReturn', [
            'report' => $this->reports->vatReturn($from, $to),
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }
}
