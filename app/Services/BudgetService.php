<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Exceptions\BudgetImportException;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Repositories\Contracts\BudgetRepositoryInterface;
use App\Repositories\Contracts\CostCenterRepositoryInterface;
use App\Support\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Yearly budgets with a monthly amount per revenue/expense account
 * (optionally within one cost center), and the comparison against what the
 * ledger actually shows. Budget and actual are always in the account's own
 * direction (revenue earned, expense incurred), exactly as the P&L shows them.
 */
class BudgetService
{
    public function __construct(
        private readonly BudgetRepositoryInterface $budgets,
        private readonly ReportService $reports,
        private readonly AccountRepositoryInterface $accounts,
        private readonly CostCenterRepositoryInterface $costCenters,
    ) {}

    /**
     * One row per budgeted year for the overview page.
     *
     * @return array<int, array{year: int, lines: int, revenue: float, expenses: float, net_profit: float, updated_at: string}>
     */
    public function overview(): array
    {
        return $this->budgets->all()->map(function (Budget $budget) {
            $revenue = $this->yearTotal($budget->lines, AccountType::Revenue);
            $expenses = $this->yearTotal($budget->lines, AccountType::Expense);

            return [
                'year' => $budget->year,
                'lines' => $budget->lines->count(),
                'revenue' => $revenue,
                'expenses' => $expenses,
                'net_profit' => round($revenue - $expenses, 2),
                'updated_at' => $budget->updated_at->toDateString(),
            ];
        })->values()->all();
    }

    /**
     * Everything the budget editor needs: the saved lines, plus last year's
     * budget and actuals so the screen can offer to pre-fill from them.
     *
     * @return array{
     *     year: int, exists: bool,
     *     lines: array<int, array{account_id: int, cost_center_id: ?int, months: array<int, float>}>,
     *     previous_budget: array<int, array{account_id: int, cost_center_id: ?int, months: array<int, float>}>,
     *     previous_actuals: array<int, array<int, float>>,
     * }
     */
    public function editorData(int $year): array
    {
        $lines = $this->budgets->linesForYears([$year, $year - 1]);

        return [
            'year' => $year,
            'exists' => $this->budgets->findForYear($year) !== null,
            'lines' => $this->formatLines($lines->where('budget.year', $year)),
            'previous_budget' => $this->formatLines($lines->where('budget.year', $year - 1)),
            'previous_actuals' => $this->reports->actualsByAccountAndMonth($year - 1),
        ];
    }

    /**
     * The revenue and expense accounts that can be budgeted (parents only
     * group, so they are left out) — the rows of the import template.
     *
     * @return array<int, array{code: string, name: string, type: string}>
     */
    public function templateAccounts(): array
    {
        $accounts = $this->accounts->all();
        $parentIds = $accounts->pluck('parent_id')->filter()->unique();

        return $accounts
            ->filter(fn ($account) => in_array($account->type, [AccountType::Revenue, AccountType::Expense], true) && ! $parentIds->contains($account->id))
            ->sortBy(fn ($account) => $account->type === AccountType::Revenue ? '0'.$account->code : '1'.$account->code)
            ->map(fn ($account) => ['code' => $account->code, 'name' => $account->name, 'type' => $account->type->value])
            ->values()
            ->all();
    }

    /**
     * Names of the active cost centers — the "Applies to" dropdown in the
     * budget workbooks.
     *
     * @return array<int, string>
     */
    public function costCenterNames(): array
    {
        return $this->costCenters->all()->pluck('name')->values()->all();
    }

    /**
     * The saved budget as spreadsheet rows in the same layout the import
     * reads (code, account, applies to, Jan–Dec) plus a year total, so an
     * export can be edited in Excel and imported back.
     *
     * @return array<int, array<int, mixed>>
     */
    public function exportRows(int $year): array
    {
        $centerNames = $this->costCenters->allIncludingInactive()->pluck('name', 'id');

        return $this->budgets->linesForYears([$year])
            ->where('budget.year', $year)
            ->sortBy(fn (BudgetLine $line) => ($line->account->type === AccountType::Revenue ? '0' : '1').$line->account->code)
            ->map(function (BudgetLine $line) use ($centerNames) {
                $months = array_values($line->monthlyAmounts());

                return [$line->account->code, $line->account->name, $line->cost_center_id ? $centerNames->get($line->cost_center_id) : null, ...$months, round(array_sum($months), 2)];
            })
            ->values()
            ->all();
    }

    /**
     * Matches imported rows to real accounts (by code) and cost centers (by
     * name) and returns them as editor lines, ready to fill the budget page
     * for review. Nothing is saved. Every problem is reported at once.
     *
     * @param  array<int, array{row: int, code: string, cost_center: ?string, months: array<int, float>}>  $rows
     * @return array<int, array{account_id: int, cost_center_id: ?int, months: array<int, float>}>
     *
     * @throws BudgetImportException
     */
    public function resolveImport(array $rows): array
    {
        $accounts = $this->accounts->all()->keyBy(fn ($account) => strtolower($account->code));
        $centers = $this->costCenters->allIncludingInactive()->keyBy(fn ($center) => strtolower($center->name));
        $errors = [];
        $lines = [];

        foreach ($rows as $row) {
            $account = $accounts->get(strtolower($row['code']));

            if ($account === null) {
                $errors[] = "Row {$row['row']}: there is no account with code \"{$row['code']}\".";

                continue;
            }

            if (! in_array($account->type, [AccountType::Revenue, AccountType::Expense], true)) {
                $errors[] = "Row {$row['row']}: {$account->code} {$account->name} is not a revenue or expense account, so it cannot be budgeted.";

                continue;
            }

            $centerId = null;

            if ($row['cost_center'] !== null) {
                $center = $centers->get(strtolower($row['cost_center']));

                if ($center === null) {
                    $errors[] = "Row {$row['row']}: there is no cost center named \"{$row['cost_center']}\".";

                    continue;
                }

                $centerId = $center->id;
            }

            $lines[] = ['account_id' => $account->id, 'cost_center_id' => $centerId, 'months' => $row['months']];
        }

        if ($errors !== []) {
            throw new BudgetImportException($errors);
        }

        return $lines;
    }

    /**
     * @param  array<int, array{account_id: int, cost_center_id?: ?int, months: array<int|string, numeric>}>  $lines
     */
    public function save(int $year, array $lines): Budget
    {
        $merged = [];

        // The same account + cost center entered twice is one budget line.
        foreach ($lines as $line) {
            $key = $line['account_id'].'|'.($line['cost_center_id'] ?? '');
            $months = [];

            foreach (BudgetLine::MONTHS as $month) {
                $months[$month] = round((float) ($line['months'][$month] ?? $line['months'][(string) $month] ?? 0), 2);
            }

            $merged[$key] ??= ['account_id' => (int) $line['account_id'], 'cost_center_id' => isset($line['cost_center_id']) ? (int) $line['cost_center_id'] : null, 'months' => array_fill_keys(BudgetLine::MONTHS, 0.0)];

            foreach ($months as $month => $amount) {
                $merged[$key]['months'][$month] = round($merged[$key]['months'][$month] + $amount, 2);
            }
        }

        return $this->budgets->replaceLines($year, (int) app(TenantContext::class)->id(), array_values($merged));
    }

    public function delete(int $year): void
    {
        $budget = $this->budgets->findForYear($year);

        if ($budget !== null) {
            $this->budgets->delete($budget);
        }
    }

    /**
     * The budget for each account over [$from, $to]. A month only partly
     * inside the range counts for the share of its days that fall inside.
     *
     * @return array<int, array{account_id: int, code: string, name: string, type: string, budget: float}>
     */
    public function budgetByAccount(string $from, string $to, int|string|null $costCenterFilter = null): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        if ($end->lt($start)) {
            return [];
        }

        $lines = $this->budgets->linesForYears(range($start->year, $end->year));
        $lines = $this->filterByCostCenter($lines, $costCenterFilter);

        $rows = [];

        for ($cursor = $start->copy()->startOfMonth(); $cursor->lte($end); $cursor->addMonthNoOverflow()) {
            $monthStart = $cursor->copy();
            $monthEnd = $cursor->copy()->endOfMonth()->startOfDay();
            $overlapDays = max($start, $monthStart)->diffInDays(min($end, $monthEnd)) + 1;
            $share = $overlapDays / $monthStart->daysInMonth;

            foreach ($lines->where('budget.year', $cursor->year) as $line) {
                $amount = $line->getAttribute('m'.$cursor->month) * $share;

                $rows[$line->account_id] ??= [
                    'account_id' => $line->account_id,
                    'code' => $line->account->code,
                    'name' => $line->account->name,
                    'type' => $line->account->type->value,
                    'budget' => 0.0,
                ];
                $rows[$line->account_id]['budget'] += $amount;
            }
        }

        return collect($rows)->map(fn (array $row) => [...$row, 'budget' => round($row['budget'], 2)])->all();
    }

    /**
     * Budget against actual for every revenue and expense account over a period.
     *
     * @return array{
     *     from: string, to: string, has_budget: bool,
     *     revenue: array<int, array<string, mixed>>,
     *     expenses: array<int, array<string, mixed>>,
     *     total_revenue: array<string, mixed>, total_expenses: array<string, mixed>, net_profit: array<string, mixed>,
     * }
     */
    public function budgetVsActual(string $from, string $to, int|string|null $costCenterFilter = null): array
    {
        $budget = $this->budgetByAccount($from, $to, $costCenterFilter);
        $actuals = $this->reports->actualsByAccount($from, $to, $costCenterFilter);

        $revenue = $this->compareRows($budget, $actuals['revenue'], AccountType::Revenue);
        $expenses = $this->compareRows($budget, $actuals['expenses'], AccountType::Expense);

        $totalRevenue = $this->totalRow($revenue, AccountType::Revenue);
        $totalExpenses = $this->totalRow($expenses, AccountType::Expense);

        $netBudget = round($totalRevenue['budget'] - $totalExpenses['budget'], 2);
        $netActual = round($totalRevenue['actual'] - $totalExpenses['actual'], 2);

        return [
            'from' => $from,
            'to' => $to,
            'has_budget' => $budget !== [],
            'revenue' => $revenue,
            'expenses' => $expenses,
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'net_profit' => [
                'budget' => $netBudget,
                'actual' => $netActual,
                'variance' => round($netActual - $netBudget, 2),
                'percent_used' => null,
                'health' => $netBudget === 0.0 && $netActual === 0.0 ? 'none' : ($netActual >= $netBudget ? 'good' : 'bad'),
            ],
        ];
    }

    /**
     * Adds a budget figure (and the accounts that are budgeted but had no
     * activity yet) to a profitAndLoss() report, for the "show budget" view.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public function annotateProfitAndLoss(array $report): array
    {
        $budget = $this->budgetByAccount($report['from'], $report['to']);
        $report['has_budget'] = $budget !== [];

        foreach (['revenue' => AccountType::Revenue, 'expenses' => AccountType::Expense] as $section => $type) {
            $rows = collect($report[$section])->keyBy('account_id');

            foreach ($budget as $accountId => $line) {
                if ($line['type'] === $type->value && ! $rows->has($accountId) && $line['budget'] !== 0.0) {
                    $rows->put($accountId, ['account_id' => $accountId, 'code' => $line['code'], 'name' => $line['name'], 'current' => 0.0, 'prior' => 0.0]);
                }
            }

            $rows = $rows->map(function (array $row) use ($budget, $type) {
                $amount = (float) ($budget[$row['account_id']]['budget'] ?? 0.0);

                return [...$row, 'budget' => $amount, 'variance' => $this->variance($type, $amount, (float) $row['current'])];
            })->sortBy('code')->values();

            $report[$section] = $rows->all();
            $totalBudget = round((float) $rows->sum('budget'), 2);
            $report['total_'.$section]['budget'] = $totalBudget;
            $report['total_'.$section]['variance'] = $this->variance($type, $totalBudget, (float) $report['total_'.$section]['current']);
        }

        $netBudget = round($report['total_revenue']['budget'] - $report['total_expenses']['budget'], 2);
        $report['net_profit']['budget'] = $netBudget;
        $report['net_profit']['variance'] = round($report['net_profit']['current'] - $netBudget, 2);

        return $report;
    }

    /**
     * Budget against actual for a whole period in three numbers, for the dashboard.
     *
     * @return array{has_budget: bool, revenue: array{budget: float, actual: float}, expenses: array{budget: float, actual: float}}
     */
    public function summary(string $from, string $to): array
    {
        $report = $this->budgetVsActual($from, $to);

        return [
            'has_budget' => $report['has_budget'],
            'revenue' => ['budget' => $report['total_revenue']['budget'], 'actual' => $report['total_revenue']['actual']],
            'expenses' => ['budget' => $report['total_expenses']['budget'], 'actual' => $report['total_expenses']['actual']],
        ];
    }

    /**
     * Year-to-date budget and actual per account, for the chart of accounts list.
     *
     * @return array<int, array{budget: float, actual: float, year_budget: float}>
     */
    public function yearToDateByAccount(int $year): array
    {
        $from = "{$year}-01-01";
        $to = min(now()->toDateString(), "{$year}-12-31");

        if ($to < $from) {
            $to = $from;
        }

        $budget = $this->budgetByAccount($from, $to);
        $yearBudget = $this->budgetByAccount($from, "{$year}-12-31");

        if ($yearBudget === []) {
            return [];
        }

        $actuals = $this->reports->actualsByAccount($from, $to);
        $actualById = collect([...$actuals['revenue'], ...$actuals['expenses']])->keyBy('account_id');

        return collect($yearBudget)->map(fn (array $line, int $accountId) => [
            'budget' => $budget[$accountId]['budget'] ?? 0.0,
            'actual' => (float) ($actualById->get($accountId)['current'] ?? 0.0),
            'year_budget' => $line['budget'],
        ])->all();
    }

    /**
     * @param  Collection<int, BudgetLine>  $lines
     */
    private function yearTotal(Collection $lines, AccountType $type): float
    {
        return round((float) $lines
            ->filter(fn (BudgetLine $line) => $line->account?->type === $type)
            ->sum(fn (BudgetLine $line) => array_sum($line->monthlyAmounts())), 2);
    }

    /**
     * @param  Collection<int, BudgetLine>  $lines
     * @return array<int, array{account_id: int, cost_center_id: ?int, months: array<int, float>}>
     */
    private function formatLines(Collection $lines): array
    {
        return $lines
            ->sortBy(fn (BudgetLine $line) => $line->account->code)
            ->map(fn (BudgetLine $line) => [
                'account_id' => $line->account_id,
                'cost_center_id' => $line->cost_center_id,
                'months' => $line->monthlyAmounts(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, BudgetLine>  $lines
     * @return Collection<int, BudgetLine>
     */
    private function filterByCostCenter(Collection $lines, int|string|null $costCenterFilter): Collection
    {
        return match (true) {
            $costCenterFilter === null => $lines,
            $costCenterFilter === 'unassigned' => $lines->whereNull('cost_center_id'),
            default => $lines->where('cost_center_id', $costCenterFilter),
        };
    }

    /**
     * @param  array<int, array{account_id: int, code: string, name: string, type: string, budget: float}>  $budget
     * @param  array<int, array{account_id: int, code: string, name: string, current: float}>  $actuals
     * @return array<int, array<string, mixed>>
     */
    private function compareRows(array $budget, array $actuals, AccountType $type): array
    {
        $rows = [];

        foreach ($budget as $accountId => $line) {
            if ($line['type'] === $type->value) {
                $rows[$accountId] = ['account_id' => $accountId, 'code' => $line['code'], 'name' => $line['name'], 'budget' => $line['budget'], 'actual' => 0.0];
            }
        }

        foreach ($actuals as $accountId => $line) {
            $rows[$accountId] ??= ['account_id' => $accountId, 'code' => $line['code'], 'name' => $line['name'], 'budget' => 0.0, 'actual' => 0.0];
            $rows[$accountId]['actual'] = $line['current'];
        }

        return collect($rows)
            ->filter(fn (array $row) => $row['budget'] !== 0.0 || $row['actual'] !== 0.0)
            ->map(fn (array $row) => $this->withVariance($row, $type))
            ->sortBy('code')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function totalRow(array $rows, AccountType $type): array
    {
        return $this->withVariance([
            'budget' => round(array_sum(array_column($rows, 'budget')), 2),
            'actual' => round(array_sum(array_column($rows, 'actual')), 2),
        ], $type);
    }

    /**
     * Variance is favourable when positive: more revenue than planned, or
     * less spending than planned. percent_used is actual as a share of budget.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function withVariance(array $row, AccountType $type): array
    {
        $budget = (float) $row['budget'];
        $actual = (float) $row['actual'];
        $percent = $budget > 0 ? round($actual / $budget * 100, 1) : null;

        return [
            ...$row,
            'variance' => $this->variance($type, $budget, $actual),
            'percent_used' => $percent,
            'health' => $this->health($type, $budget, $actual, $percent),
        ];
    }

    private function variance(AccountType $type, float $budget, float $actual): float
    {
        return round($type === AccountType::Revenue ? $actual - $budget : $budget - $actual, 2);
    }

    /**
     * 'good', 'warn' or 'bad' for a coloured badge; 'none' when there is no budget to judge against.
     */
    private function health(AccountType $type, float $budget, float $actual, ?float $percent): string
    {
        if ($percent === null) {
            return 'none';
        }

        if ($type === AccountType::Expense) {
            return match (true) {
                $percent > 100 => 'bad',
                $percent >= 90 => 'warn',
                default => 'good',
            };
        }

        return match (true) {
            $percent >= 100 => 'good',
            $percent >= 80 => 'warn',
            default => 'bad',
        };
    }
}
