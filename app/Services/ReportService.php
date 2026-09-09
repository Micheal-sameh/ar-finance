<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Models\Account;
use App\Repositories\Contracts\CostCenterRepositoryInterface;
use App\Repositories\Contracts\JournalRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Every figure here is derived from posted journal_lines, never from
 * module tables (invoices/expenses/...) directly — the GL stays the
 * single source of truth for every report.
 */
class ReportService
{
    public function __construct(
        private readonly JournalRepositoryInterface $journals,
        private readonly CostCenterRepositoryInterface $costCenters,
    ) {
    }

    /**
     * @return array{
     *     rows: array<int, array{account_id: int, code: string, name: string, type: string, debit: float, credit: float}>,
     *     total_debit: float,
     *     total_credit: float,
     *     is_balanced: bool,
     * }
     */
    public function trialBalance(?string $from = null, ?string $to = null): array
    {
        $lines = $this->journals->postedLinesWithAccounts($from, $to);

        $rows = $lines
            ->groupBy('account_id')
            ->map(function ($accountLines) {
                $account = $accountLines->first()->account;
                $totalDebit = (float) $accountLines->sum('debit');
                $totalCredit = (float) $accountLines->sum('credit');
                $net = round($totalDebit - $totalCredit, 2);

                return [
                    'account_id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type->value,
                    'debit' => $net > 0 ? $net : 0.0,
                    'credit' => $net < 0 ? abs($net) : 0.0,
                ];
            })
            ->filter(fn ($row) => $row['debit'] !== 0.0 || $row['credit'] !== 0.0)
            ->sortBy('code')
            ->values()
            ->all();

        $totalDebit = round(array_sum(array_column($rows, 'debit')), 2);
        $totalCredit = round(array_sum(array_column($rows, 'credit')), 2);

        return [
            'rows' => $rows,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced' => abs($totalDebit - $totalCredit) < 0.005,
        ];
    }

    /**
     * Running balance for a single account, in date/entry order.
     *
     * @return array{
     *     lines: array<int, array{date: string, description: string, reference: ?string, debit: float, credit: float, balance: float}>,
     *     ending_balance: float,
     * }
     */
    public function generalLedger(Account $account, ?string $from = null, ?string $to = null): array
    {
        $lines = $this->journals->postedLinesForAccount($account->id, $from, $to)
            ->sortBy([
                fn ($line) => $line->journalEntry->date,
                fn ($line) => $line->journalEntry->id,
            ])
            ->values();

        $isDebitNormal = $account->normal_balance->value === 'debit';

        $balance = 0.0;
        $rows = $lines->map(function ($line) use (&$balance, $isDebitNormal) {
            $debit = (float) $line->debit;
            $credit = (float) $line->credit;
            $balance += $isDebitNormal ? ($debit - $credit) : ($credit - $debit);

            return [
                'date' => $line->journalEntry->date->toDateString(),
                'description' => $line->description ?? $line->journalEntry->description,
                'reference' => $line->journalEntry->reference,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => round($balance, 2),
            ];
        })->values()->all();

        return [
            'lines' => $rows,
            'ending_balance' => round($balance, 2),
        ];
    }

    /**
     * Revenue less expenses for a period, with an optional comparison
     * period alongside each row — computed purely from posted journal
     * lines within each date range (Revenue/Expense are "flow" accounts,
     * so unlike the balance sheet this is period-bound, not cumulative).
     *
     * @return array{
     *     from: string, to: string, compare_from: ?string, compare_to: ?string,
     *     revenue: array<int, array{account_id: int, code: string, name: string, current: float, prior: float}>,
     *     expenses: array<int, array{account_id: int, code: string, name: string, current: float, prior: float}>,
     *     total_revenue: array{current: float, prior: float},
     *     total_expenses: array{current: float, prior: float},
     *     net_profit: array{current: float, prior: float},
     * }
     */
    public function profitAndLoss(
        string $from,
        string $to,
        ?string $compareFrom = null,
        ?string $compareTo = null,
    ): array {
        $current = $this->flowBalancesByType($from, $to, [AccountType::Revenue, AccountType::Expense]);
        $prior = ($compareFrom && $compareTo)
            ? $this->flowBalancesByType($compareFrom, $compareTo, [AccountType::Revenue, AccountType::Expense])
            : collect();

        $revenue = $this->mergeComparativeRows(
            $current->get(AccountType::Revenue->value, collect()),
            $prior->get(AccountType::Revenue->value, collect()),
        );
        $expenses = $this->mergeComparativeRows(
            $current->get(AccountType::Expense->value, collect()),
            $prior->get(AccountType::Expense->value, collect()),
        );

        $totalRevenue = ['current' => round(array_sum(array_column($revenue, 'current')), 2), 'prior' => round(array_sum(array_column($revenue, 'prior')), 2)];
        $totalExpenses = ['current' => round(array_sum(array_column($expenses, 'current')), 2), 'prior' => round(array_sum(array_column($expenses, 'prior')), 2)];

        return [
            'from' => $from,
            'to' => $to,
            'compare_from' => $compareFrom,
            'compare_to' => $compareTo,
            'revenue' => $revenue,
            'expenses' => $expenses,
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'net_profit' => [
                'current' => round($totalRevenue['current'] - $totalExpenses['current'], 2),
                'prior' => round($totalRevenue['prior'] - $totalExpenses['prior'], 2),
            ],
        ];
    }

    /**
     * Assets = Liabilities + Equity as of a date. Equity includes a
     * computed "Retained Earnings (current period)" row — this system
     * has no period-close step that sweeps net income into an equity
     * account, so it's derived here instead, from all posted revenue and
     * expense activity up to $asOf.
     *
     * @return array{
     *     as_of: string,
     *     assets: array<int, array{account_id: int, code: string, name: string, balance: float}>,
     *     liabilities: array<int, array{account_id: int, code: string, name: string, balance: float}>,
     *     equity: array<int, array{account_id: int, code: string, name: string, balance: float}>,
     *     total_assets: float,
     *     total_liabilities: float,
     *     total_equity: float,
     *     is_balanced: bool,
     * }
     */
    public function balanceSheet(string $asOf): array
    {
        $lines = $this->journals->postedLinesWithAccounts(null, $asOf);

        $byType = $this->cumulativeBalancesByType($lines);

        $assets = $byType->get(AccountType::Asset->value, collect())->values()->all();
        $liabilities = $byType->get(AccountType::Liability->value, collect())->values()->all();
        $equity = $byType->get(AccountType::Equity->value, collect())->values()->all();

        $totalRevenue = (float) $lines->filter(fn ($line) => $line->account->type === AccountType::Revenue)
            ->sum(fn ($line) => (float) $line->credit - (float) $line->debit);
        $totalExpense = (float) $lines->filter(fn ($line) => $line->account->type === AccountType::Expense)
            ->sum(fn ($line) => (float) $line->debit - (float) $line->credit);
        $retainedEarnings = round($totalRevenue - $totalExpense, 2);

        if ($retainedEarnings !== 0.0) {
            $equity[] = [
                'account_id' => 0,
                'code' => '',
                'name' => 'Retained Earnings (current period)',
                'balance' => $retainedEarnings,
            ];
        }

        $totalAssets = round(array_sum(array_column($assets, 'balance')), 2);
        $totalLiabilities = round(array_sum(array_column($liabilities, 'balance')), 2);
        $totalEquity = round(array_sum(array_column($equity, 'balance')), 2);

        return [
            'as_of' => $asOf,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'is_balanced' => abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.005,
        ];
    }

    /**
     * Groups posted lines in a date range by account type, summing each
     * account's natural-direction net (credit-debit for revenue,
     * debit-credit for expense) — a period total, not a running balance.
     *
     * @param  AccountType[]  $types
     * @return Collection<string, Collection<int, array{account_id: int, code: string, name: string, current: float}>>
     */
    private function flowBalancesByType(string $from, string $to, array $types): Collection
    {
        $lines = $this->journals->postedLinesWithAccounts($from, $to)
            ->filter(fn ($line) => in_array($line->account->type, $types, true));

        return $lines->groupBy(fn ($line) => $line->account->type->value)
            ->map(function ($typeLines) {
                return $typeLines->groupBy('account_id')->map(function ($accountLines) {
                    $account = $accountLines->first()->account;
                    $isRevenue = $account->type === AccountType::Revenue;
                    $net = $isRevenue
                        ? (float) $accountLines->sum('credit') - (float) $accountLines->sum('debit')
                        : (float) $accountLines->sum('debit') - (float) $accountLines->sum('credit');

                    return [
                        'account_id' => $account->id,
                        'code' => $account->code,
                        'name' => $account->name,
                        'current' => round($net, 2),
                    ];
                })->filter(fn ($row) => $row['current'] !== 0.0);
            });
    }

    /**
     * Groups posted lines (already filtered to a date range by the
     * caller) by account type, summing each account's balance in its own
     * normal-balance direction — a cumulative running balance, for
     * balance-sheet accounts.
     *
     * @return Collection<string, Collection<int, array{account_id: int, code: string, name: string, balance: float}>>
     */
    private function cumulativeBalancesByType(Collection $lines): Collection
    {
        $balanceSheetTypes = [AccountType::Asset, AccountType::Liability, AccountType::Equity];

        return $lines
            ->filter(fn ($line) => in_array($line->account->type, $balanceSheetTypes, true))
            ->groupBy(fn ($line) => $line->account->type->value)
            ->map(function ($typeLines) {
                return $typeLines->groupBy('account_id')->map(function ($accountLines) {
                    $account = $accountLines->first()->account;
                    $isDebitNormal = $account->normal_balance->value === 'debit';
                    $net = $isDebitNormal
                        ? (float) $accountLines->sum('debit') - (float) $accountLines->sum('credit')
                        : (float) $accountLines->sum('credit') - (float) $accountLines->sum('debit');

                    return [
                        'account_id' => $account->id,
                        'code' => $account->code,
                        'name' => $account->name,
                        'balance' => round($net, 2),
                    ];
                })->filter(fn ($row) => $row['balance'] !== 0.0)->sortBy('code');
            });
    }

    /**
     * Unions current + prior rows by account_id so a P&L section shows
     * every account active in either period, zero-filled on the side
     * where it had no activity.
     *
     * @return array<int, array{account_id: int, code: string, name: string, current: float, prior: float}>
     */
    private function mergeComparativeRows(Collection $current, Collection $prior): array
    {
        $accountIds = $current->pluck('account_id')->merge($prior->pluck('account_id'))->unique();

        return $accountIds->map(function ($accountId) use ($current, $prior) {
            $currentRow = $current->firstWhere('account_id', $accountId);
            $priorRow = $prior->firstWhere('account_id', $accountId);
            $base = $currentRow ?? $priorRow;

            return [
                'account_id' => $base['account_id'],
                'code' => $base['code'],
                'name' => $base['name'],
                'current' => $currentRow['current'] ?? 0.0,
                'prior' => $priorRow['current'] ?? 0.0,
            ];
        })->sortBy('code')->values()->all();
    }

    /**
     * Budget vs actual per active cost center, for a period. "Spent" is
     * expense-type activity tagged to the center (what a budget tracks);
     * "revenue" is included too so profit centers show their net
     * contribution, not just spend. Centers with no tagged activity in
     * the period still appear, at zero.
     *
     * @return array<int, array{
     *     cost_center_id: int, name: string, type: string, budget: ?float,
     *     spent: float, revenue: float, net: float, utilization_percent: ?float,
     * }>
     */
    public function costCenterSummary(?string $from = null, ?string $to = null): array
    {
        $centers = $this->costCenters->all();

        $actuals = $this->journals->postedLinesWithAccounts($from, $to)
            ->filter(fn ($line) => $line->cost_center_id !== null)
            ->groupBy('cost_center_id')
            ->map(fn ($lines) => [
                'spent' => round(
                    (float) $lines->filter(fn ($line) => $line->account->type === AccountType::Expense)
                        ->sum(fn ($line) => (float) $line->debit - (float) $line->credit),
                    2,
                ),
                'revenue' => round(
                    (float) $lines->filter(fn ($line) => $line->account->type === AccountType::Revenue)
                        ->sum(fn ($line) => (float) $line->credit - (float) $line->debit),
                    2,
                ),
            ]);

        return $centers->map(function ($center) use ($actuals) {
            $actual = $actuals->get($center->id, ['spent' => 0.0, 'revenue' => 0.0]);
            $budget = $center->budget !== null ? (float) $center->budget : null;

            return [
                'cost_center_id' => $center->id,
                'name' => $center->name,
                'type' => $center->type->value,
                'budget' => $budget,
                'spent' => $actual['spent'],
                'revenue' => $actual['revenue'],
                'net' => round($actual['revenue'] - $actual['spent'], 2),
                'utilization_percent' => ($budget && $budget > 0) ? round(($actual['spent'] / $budget) * 100, 1) : null,
            ];
        })->values()->all();
    }
}
