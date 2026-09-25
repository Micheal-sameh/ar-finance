<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsExcel;
use App\Http\Controllers\Concerns\GeneratesPdf;
use App\Models\Account;
use App\Services\AccountService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ExportsExcel;
    use GeneratesPdf;

    public function __construct(
        private readonly ReportService $reports,
        private readonly AccountService $accounts,
    ) {}

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

    public function trialBalancePdf(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Account::class);

        $from = $request->string('from')->value() ?: null;
        $to = $request->string('to')->value() ?: null;

        return $this->downloadPdf('trial-balance.pdf', 'pdf.reports.trial-balance', [
            'tenant' => auth()->user()->tenant,
            'report' => $this->reports->trialBalance($from, $to),
            'from' => $from,
            'to' => $to,
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

    public function profitAndLossExport(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Account::class);

        $from = $request->string('from')->value() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->value() ?: now()->toDateString();
        $compareFrom = $request->string('compare_from')->value() ?: null;
        $compareTo = $request->string('compare_to')->value() ?: null;

        $report = $this->reports->profitAndLoss($from, $to, $compareFrom, $compareTo);

        $rows = collect();

        foreach ($report['revenue'] as $row) {
            $rows->push(['Revenue', $row['code'], $row['name'], $row['current'], $row['prior']]);
        }
        $rows->push(['Revenue', '', 'Total Revenue', $report['total_revenue']['current'], $report['total_revenue']['prior']]);

        foreach ($report['expenses'] as $row) {
            $rows->push(['Expense', $row['code'], $row['name'], $row['current'], $row['prior']]);
        }
        $rows->push(['Expense', '', 'Total Expenses', $report['total_expenses']['current'], $report['total_expenses']['prior']]);

        $rows->push(['', '', 'Net Profit', $report['net_profit']['current'], $report['net_profit']['prior']]);

        return $this->exportXlsx('profit-and-loss.xlsx', ['Section', 'Code', 'Account', 'Amount', 'Prior Period'], $rows);
    }

    public function profitAndLossPdf(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Account::class);

        $from = $request->string('from')->value() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->value() ?: now()->toDateString();
        $compareFrom = $request->string('compare_from')->value() ?: null;
        $compareTo = $request->string('compare_to')->value() ?: null;

        return $this->downloadPdf('profit-and-loss.pdf', 'pdf.reports.profit-and-loss', [
            'tenant' => auth()->user()->tenant,
            'report' => $this->reports->profitAndLoss($from, $to, $compareFrom, $compareTo),
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

    public function balanceSheetPdf(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Account::class);

        $asOf = $request->string('as_of')->value() ?: now()->toDateString();

        return $this->downloadPdf('balance-sheet.pdf', 'pdf.reports.balance-sheet', [
            'tenant' => auth()->user()->tenant,
            'report' => $this->reports->balanceSheet($asOf),
        ]);
    }

    public function cashFlow(Request $request): Response
    {
        $this->authorize('viewAny', Account::class);

        $from = $request->string('from')->value() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->value() ?: now()->toDateString();

        return Inertia::render('Accounting/Reports/CashFlow', [
            'report' => $this->reports->cashFlow($from, $to),
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function cashFlowPdf(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Account::class);

        $from = $request->string('from')->value() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->value() ?: now()->toDateString();

        return $this->downloadPdf('cash-flow.pdf', 'pdf.reports.cash-flow', [
            'tenant' => auth()->user()->tenant,
            'report' => $this->reports->cashFlow($from, $to),
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

    public function aging(Request $request): Response
    {
        $this->authorize('viewAny', Account::class);

        $asOf = $request->string('as_of')->value() ?: now()->toDateString();

        return Inertia::render('Accounting/Reports/Aging', [
            'arReport' => $this->reports->arAging($asOf),
            'apReport' => $this->reports->apAging($asOf),
            'filters' => ['as_of' => $asOf],
        ]);
    }
}
