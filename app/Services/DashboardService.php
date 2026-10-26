<?php

namespace App\Services;

use App\Models\JournalEntry;
use Illuminate\Support\Carbon;

/**
 * Assembles the home-page KPI snapshot from data other services already
 * compute — cash from bank-linked account balances, AR/AP from the aging
 * report, revenue/expense from P&L — rather than re-deriving any of it
 * from journal_lines directly.
 */
class DashboardService
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly JournalService $journals,
        private readonly BankAccountService $bankAccounts,
        private readonly BudgetService $budgets,
    ) {}

    /**
     * @return array{
     *     cash: float,
     *     receivables: float,
     *     payables: float,
     *     revenue_month: float,
     *     expenses_month: float,
     *     net_profit_month: float,
     *     period: array{from: string, to: string},
     *     previous: array{revenue: float, expenses: float, net_profit: float},
     *     trend: array<int, array{month: string, revenue: float, expenses: float}>,
     *     expense_breakdown: array<int, array{label: string, amount: float, percent: float}>,
     *     overdue: array{total: float, count: int, rows: array<int, array{id: int, name: string, amount: float, age_label: string}>},
     *     recent_activity: array<int, array{id: int, date: string, description: string, reference: ?string, source_label: string, amount: float}>,
     *     budget: array{has_budget: bool, revenue: array{budget: float, actual: float}, expenses: array{budget: float, actual: float}},
     * }
     */
    public function summary(?string $from = null, ?string $to = null): array
    {
        $from ??= now()->startOfMonth()->toDateString();
        $to ??= now()->toDateString();

        // The comparison period is the span of equal length immediately before the selected one.
        $days = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $previousTo = Carbon::parse($from)->subDay();
        $previousFrom = $previousTo->copy()->subDays($days - 1);

        $balances = $this->reports->accountBalances();
        $cash = round(
            $this->bankAccounts->all()->sum(fn ($bankAccount) => $balances[$bankAccount->account_id] ?? 0.0),
            2,
        );

        $ar = $this->reports->arAging($to);
        $ap = $this->reports->apAging($to);
        $pnl = $this->reports->profitAndLoss($from, $to, $previousFrom->toDateString(), $previousTo->toDateString());

        return [
            'cash' => $cash,
            'receivables' => $ar['totals']['total'],
            'payables' => $ap['totals']['total'],
            'revenue_month' => $pnl['total_revenue']['current'],
            'expenses_month' => $pnl['total_expenses']['current'],
            'net_profit_month' => $pnl['net_profit']['current'],
            'period' => ['from' => $from, 'to' => $to],
            'previous' => [
                'revenue' => $pnl['total_revenue']['prior'],
                'expenses' => $pnl['total_expenses']['prior'],
                'net_profit' => $pnl['net_profit']['prior'],
            ],
            'trend' => $this->monthlyTrend(12, Carbon::parse($to)),
            'expense_breakdown' => $this->expenseBreakdown($pnl['expenses']),
            'overdue' => $this->overdueSummary($ar['rows']),
            'recent_activity' => $this->recentActivity(8),
            'budget' => $this->budgets->summary($from, $to),
        ];
    }

    /**
     * Top four expense accounts for the month plus an "Other" remainder, as
     * a share of total expenses.
     *
     * @param  array<int, array{name: string, current: float}>  $expenses
     * @return array<int, array{label: string, amount: float, percent: float}>
     */
    private function expenseBreakdown(array $expenses): array
    {
        $sorted = collect($expenses)->filter(fn ($row) => $row['current'] > 0)->sortByDesc('current')->values();
        $total = (float) $sorted->sum('current');

        if ($total <= 0) {
            return [];
        }

        $rows = $sorted->take(4)->map(fn ($row) => ['label' => $row['name'], 'amount' => round($row['current'], 2)]);
        $remainder = round($total - $rows->sum('amount'), 2);

        if ($remainder > 0) {
            $rows->push(['label' => 'Other', 'amount' => $remainder]);
        }

        return $rows->map(fn ($row) => $row + ['percent' => round($row['amount'] / $total * 100, 1)])->all();
    }

    /**
     * Receivables past their due date, per client, largest first.
     *
     * @param  array<int, array{id: int, name: string, days_1_30: float, days_31_60: float, days_61_90: float, days_90_plus: float}>  $agingRows
     * @return array{total: float, count: int, rows: array<int, array{id: int, name: string, amount: float, age_label: string}>}
     */
    private function overdueSummary(array $agingRows): array
    {
        $buckets = ['days_90_plus' => '90+ days', 'days_61_90' => '61–90 days', 'days_31_60' => '31–60 days', 'days_1_30' => '1–30 days'];

        $rows = collect($agingRows)
            ->map(function (array $row) use ($buckets) {
                $amount = round($row['days_1_30'] + $row['days_31_60'] + $row['days_61_90'] + $row['days_90_plus'], 2);
                $oldest = collect($buckets)->first(fn ($label, $key) => $row[$key] > 0);

                return ['id' => $row['id'], 'name' => $row['name'], 'amount' => $amount, 'age_label' => $oldest ?? ''];
            })
            ->filter(fn ($row) => $row['amount'] > 0)
            ->sortByDesc('amount')
            ->values();

        return [
            'total' => round((float) $rows->sum('amount'), 2),
            'count' => $rows->count(),
            'rows' => $rows->take(4)->all(),
        ];
    }

    /**
     * @return array<int, array{month: string, revenue: float, expenses: float}>
     */
    private function monthlyTrend(int $months, Carbon $endingAt): array
    {
        $periods = [];
        $labels = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $start = $endingAt->copy()->subMonthsNoOverflow($i)->startOfMonth();
            $periods[$start->format('Y-m')] = [$start->toDateString(), $endingAt->copy()->subMonthsNoOverflow($i)->endOfMonth()->toDateString()];
            $labels[$start->format('Y-m')] = $start->format('M Y');
        }

        $totals = $this->reports->revenueAndExpensesByPeriod($periods);

        return collect($labels)->map(fn (string $label, string $key) => [
            'month' => $label,
            'revenue' => $totals[$key]['revenue'],
            'expenses' => $totals[$key]['expenses'],
        ])->values()->all();
    }

    /**
     * @return array<int, array{id: int, date: string, description: string, reference: ?string, source_label: string, amount: float}>
     */
    private function recentActivity(int $limit): array
    {
        return $this->journals->paginate([], $limit)->getCollection()
            ->map(fn (JournalEntry $entry) => [
                'id' => $entry->id,
                'date' => $entry->date->toDateString(),
                'description' => $entry->description,
                'reference' => $entry->reference,
                'source_label' => $entry->source_type?->label() ?? 'Manual',
                'amount' => round((float) $entry->lines->sum('debit'), 2),
            ])
            ->values()
            ->all();
    }
}
