<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsExcel;
use App\Http\Controllers\Concerns\GeneratesPdf;
use App\Models\Account;
use App\Models\CostCenter;
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

        $from = $request->string('from')->value() ?: now()->startOfYear()->toDateString();
        $to = $request->string('to')->value() ?: now()->toDateString();

        return Inertia::render('Accounting/Reports/TrialBalance', [
            'report' => $this->reports->trialBalance($from, $to),
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function trialBalancePdf(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Account::class);

        $from = $request->string('from')->value() ?: now()->startOfYear()->toDateString();
        $to = $request->string('to')->value() ?: now()->toDateString();

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
        $costCenterFilter = $this->costCenterFilter($request);

        $account = $accountId ? $this->accounts->find($accountId) : null;
        $costCenter = is_int($costCenterFilter) ? CostCenter::find($costCenterFilter) : null;

        return Inertia::render('Accounting/Reports/GeneralLedger', [
            'accounts' => $this->accounts->all(),
            'account' => $account,
            'ledger' => $account ? $this->reports->generalLedger($account, $from, $to, $costCenterFilter) : null,
            'costCenterLabel' => match (true) {
                $costCenter !== null => $costCenter->name,
                $costCenterFilter === 'unassigned' => 'Unassigned',
                default => null,
            },
            'filters' => ['account_id' => $accountId, 'from' => $from, 'to' => $to, 'cost_center_id' => $costCenterFilter],
        ]);
    }

    /**
     * Reads `cost_center_id` as an int, the literal string 'unassigned',
     * or null — the same convention ReportService's cost-center filtering
     * uses throughout.
     */
    private function costCenterFilter(Request $request): int|string|null
    {
        $value = $request->string('cost_center_id')->value() ?: null;

        if ($value === 'unassigned') {
            return 'unassigned';
        }

        return $value !== null ? (int) $value : null;
    }

    public function profitAndLoss(Request $request): Response
    {
        $this->authorize('viewAny', Account::class);

        $from = $request->string('from')->value() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->value() ?: now()->toDateString();
        $compareFrom = $request->string('compare_from')->value() ?: null;
        $compareTo = $request->string('compare_to')->value() ?: null;
        $groupBy = $this->groupBy($request);

        return Inertia::render('Accounting/Reports/ProfitAndLoss', [
            'report' => $groupBy
                ? $this->reports->profitAndLossGrouped($from, $to, $groupBy)
                : $this->reports->profitAndLoss($from, $to, $compareFrom, $compareTo),
            'filters' => [
                'from' => $from,
                'to' => $to,
                'compare_from' => $compareFrom,
                'compare_to' => $compareTo,
                'group_by' => $groupBy,
            ],
        ]);
    }

    public function profitAndLossExport(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Account::class);

        $from = $request->string('from')->value() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->value() ?: now()->toDateString();
        $compareFrom = $request->string('compare_from')->value() ?: null;
        $compareTo = $request->string('compare_to')->value() ?: null;
        $groupBy = $this->groupBy($request);

        if ($groupBy) {
            $report = $this->reports->profitAndLossGrouped($from, $to, $groupBy);
            $headings = ['Section', 'Code', 'Account', ...array_column($report['columns'], 'label'), 'Total'];

            $rows = collect();
            foreach (['Revenue' => $report['revenue'], 'Expense' => $report['expenses']] as $section => $sectionRows) {
                foreach ($sectionRows as $row) {
                    $rows->push([$section, $row['code'], $row['name'], ...array_values($row['amounts']), $row['total']]);
                }
                $totals = $section === 'Revenue' ? $report['total_revenue'] : $report['total_expenses'];
                $rows->push([$section, '', "Total {$section}", ...array_values($totals['amounts']), $totals['total']]);
            }
            $rows->push(['', '', 'Net Profit', ...array_values($report['net_profit']['amounts']), $report['net_profit']['total']]);

            return $this->exportXlsx('profit-and-loss.xlsx', $headings, $rows);
        }

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
        $groupBy = $this->groupBy($request);

        return $this->downloadPdf('profit-and-loss.pdf', 'pdf.reports.profit-and-loss', [
            'tenant' => auth()->user()->tenant,
            'report' => $groupBy
                ? $this->reports->profitAndLossGrouped($from, $to, $groupBy)
                : $this->reports->profitAndLoss($from, $to, $compareFrom, $compareTo),
        ]);
    }

    /**
     * Validates the `group_by` query param against the report's supported
     * groupings, so an unrecognized value falls back to the plain
     * current/prior view instead of erroring.
     */
    private function groupBy(Request $request): ?string
    {
        $groupBy = $request->string('group_by')->value() ?: null;

        return in_array($groupBy, ['month', 'quarter', 'cost_center'], true) ? $groupBy : null;
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
