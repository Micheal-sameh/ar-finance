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
}
