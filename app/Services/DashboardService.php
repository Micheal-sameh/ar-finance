<?php

namespace App\Services;

use App\Models\JournalEntry;

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
    ) {
    }

    /**
     * @return array{
     *     cash: float,
     *     receivables: float,
     *     payables: float,
     *     revenue_month: float,
     *     expenses_month: float,
     *     net_profit_month: float,
     *     trend: array<int, array{month: string, revenue: float, expenses: float}>,
     *     recent_activity: array<int, array{id: int, date: string, description: string, reference: ?string, source_label: string, amount: float}>,
     * }
     */
    public function summary(): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $balances = $this->reports->accountBalances();
        $cash = round(
            $this->bankAccounts->all()->sum(fn ($bankAccount) => $balances[$bankAccount->account_id] ?? 0.0),
            2,
        );

        $ar = $this->reports->arAging($today);
        $ap = $this->reports->apAging($today);
        $pnl = $this->reports->profitAndLoss($monthStart, $today);

        return [
            'cash' => $cash,
            'receivables' => $ar['totals']['total'],
            'payables' => $ap['totals']['total'],
            'revenue_month' => $pnl['total_revenue']['current'],
            'expenses_month' => $pnl['total_expenses']['current'],
            'net_profit_month' => $pnl['net_profit']['current'],
            'trend' => $this->monthlyTrend(6),
            'recent_activity' => $this->recentActivity(8),
        ];
    }

    /**
     * @return array<int, array{month: string, revenue: float, expenses: float}>
     */
    private function monthlyTrend(int $months): array
    {
        $rows = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $start = now()->subMonthsNoOverflow($i)->startOfMonth();
            $end = now()->subMonthsNoOverflow($i)->endOfMonth();
            $pnl = $this->reports->profitAndLoss($start->toDateString(), $end->toDateString());

            $rows[] = [
                'month' => $start->format('M Y'),
                'revenue' => $pnl['total_revenue']['current'],
                'expenses' => $pnl['total_expenses']['current'],
            ];
        }

        return $rows;
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
