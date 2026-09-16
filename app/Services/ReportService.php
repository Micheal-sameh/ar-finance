<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Models\Account;
use App\Repositories\Contracts\BillRepositoryInterface;
use App\Repositories\Contracts\CostCenterRepositoryInterface;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\JournalRepositoryInterface;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Every figure here is derived from posted journal_lines, never from
 * module tables (invoices/expenses/...) directly — the GL stays the
 * single source of truth for every report. The one exception is
 * vatReturn(): see its docblock for why that one reads invoice/bill
 * lines instead.
 */
class ReportService
{
    /**
     * These two equity accounts are never posted to directly — there's no
     * period-close step in this system (see balanceSheet()'s docblock).
     * Their balances are always derived instead: 3100 is this year's
     * revenue-minus-expenses so far, 3200 is every prior year's, combined.
     */
    private const NET_INCOME_CODE = '3100';

    private const RETAINED_EARNINGS_CODE = '3200';

    public function __construct(
        private readonly JournalRepositoryInterface $journals,
        private readonly CostCenterRepositoryInterface $costCenters,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly BillRepositoryInterface $bills,
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
     * Cumulative balance (as of now, all-time) for every account with any
     * posted activity, in the account's own normal-balance direction —
     * powers the "Balance" column on the Chart of Accounts list. An
     * account with no posted lines simply won't have a key here; callers
     * should default to 0.
     *
     * Bounded to today: a journal entry can be dated in the future (e.g. a
     * month-end depreciation/payroll run posted ahead of time), and this
     * must exclude those the same way 3100/3200 do below — otherwise
     * Revenue/Expense totals here would include activity Net Income
     * hasn't counted yet, and the two would stop reconciling.
     *
     * @return array<int, float> account_id => balance
     */
    public function accountBalances(): array
    {
        $balances = $this->journals->postedLinesWithAccounts(null, now()->toDateString())
            ->groupBy('account_id')
            ->map(function ($lines) {
                $account = $lines->first()->account;
                $debit = (float) $lines->sum('debit');
                $credit = (float) $lines->sum('credit');

                return $account->normal_balance->value === 'debit'
                    ? round($debit - $credit, 2)
                    : round($credit - $debit, 2);
            })
            ->all();

        $split = $this->netIncomeSplitAsOf(now()->toDateString());

        if ($netIncomeAccount = Account::where('code', self::NET_INCOME_CODE)->first()) {
            $balances[$netIncomeAccount->id] = $split['current'];
        }

        if ($retainedEarningsAccount = Account::where('code', self::RETAINED_EARNINGS_CODE)->first()) {
            $balances[$retainedEarningsAccount->id] = $split['prior'];
        }

        return $balances;
    }

    /**
     * Splits all-time net income as of $asOf into "this year so far" and
     * "every prior year, combined" — the two halves that 3100/3200 (and
     * the balance-sheet fallback row) show.
     *
     * @return array{current: float, prior: float}
     */
    private function netIncomeSplitAsOf(string $asOf): array
    {
        $yearStart = Carbon::parse($asOf)->startOfYear()->toDateString();
        $total = $this->netIncomeBetween(null, $asOf);
        $current = $this->netIncomeBetween($yearStart, $asOf);

        return [
            'current' => $current,
            'prior' => round($total - $current, 2),
        ];
    }

    /**
     * Revenue-minus-expenses across all posted activity in a date range.
     * $from/$to are inclusive; null on either side means unbounded.
     */
    private function netIncomeBetween(?string $from, ?string $to): float
    {
        $lines = $this->journals->postedLinesWithAccounts($from, $to);

        $revenue = (float) $lines->filter(fn ($line) => $line->account->type === AccountType::Revenue)
            ->sum(fn ($line) => (float) $line->credit - (float) $line->debit);
        $expense = (float) $lines->filter(fn ($line) => $line->account->type === AccountType::Expense)
            ->sum(fn ($line) => (float) $line->debit - (float) $line->credit);

        return round($revenue - $expense, 2);
    }

    /**
     * Folds net-income-to-date into the balance sheet's equity rows so
     * assets always equal liabilities plus equity. Shown split across the
     * real 3100 (Net Income)/3200 (Retained Earnings) accounts when the
     * tenant has them (any existing posted-line rows for those codes are
     * replaced, since they'd otherwise double-count); falls back to one
     * synthetic row otherwise.
     *
     * @param  array<int, array{account_id: int, code: string, name: string, balance: float}>  $equity
     * @return array<int, array{account_id: int, code: string, name: string, balance: float}>
     */
    private function withNetIncomeToDate(array $equity, string $asOf): array
    {
        $netIncomeAccount = Account::where('code', self::NET_INCOME_CODE)->first();
        $retainedEarningsAccount = Account::where('code', self::RETAINED_EARNINGS_CODE)->first();

        if (! $netIncomeAccount && ! $retainedEarningsAccount) {
            $retainedEarnings = $this->netIncomeBetween(null, $asOf);

            if ($retainedEarnings !== 0.0) {
                $equity[] = [
                    'account_id' => 0,
                    'code' => '',
                    'name' => 'Retained Earnings (current period)',
                    'balance' => $retainedEarnings,
                ];
            }

            return $equity;
        }

        $split = $this->netIncomeSplitAsOf($asOf);

        $equity = array_values(array_filter(
            $equity,
            fn ($row) => ! in_array($row['code'], [self::NET_INCOME_CODE, self::RETAINED_EARNINGS_CODE], true),
        ));

        if ($netIncomeAccount) {
            $equity[] = [
                'account_id' => $netIncomeAccount->id,
                'code' => $netIncomeAccount->code,
                'name' => $netIncomeAccount->name,
                'balance' => $split['current'],
            ];
        }

        if ($retainedEarningsAccount) {
            $equity[] = [
                'account_id' => $retainedEarningsAccount->id,
                'code' => $retainedEarningsAccount->code,
                'name' => $retainedEarningsAccount->name,
                'balance' => $split['prior'],
            ];
        }

        usort($equity, fn ($a, $b) => $a['code'] <=> $b['code']);

        return $equity;
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
     * Assets = Liabilities + Equity as of a date, always — this system has
     * no period-close step that sweeps net income into an equity account,
     * so the net-income-to-date figure is derived here from posted revenue
     * and expense activity instead, and folded into equity. When the
     * tenant has the standard 3100/3200 accounts (see
     * ChartOfAccountsSeeder), it's shown split across them, current year
     * vs. prior; otherwise it falls back to a single synthetic row so the
     * equation still balances.
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

        $equity = $this->withNetIncomeToDate($equity, $asOf);

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
     * caller) by account type, summing each account's balance in its
     * *type's* normal-balance direction — deliberately not the account's
     * own normal_balance, so a contra account (e.g. Accumulated
     * Depreciation: type Asset, but credit-normal) comes out negative and
     * correctly reduces its type's total instead of inflating it. Other
     * reports (trial balance, general ledger) still use the account's own
     * normal_balance, since a ledger card should show that account's
     * natural balance positive — only this balance-sheet total needs
     * type-uniform signing for Assets = Liabilities + Equity to hold.
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
                    $net = $account->type->isDebitNormal()
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

    /**
     * Output VAT (from sent/paid invoices) less input VAT (from
     * approved/paid bills) for a period — net VAT payable to the
     * authority.
     *
     * Unlike every other report here, this reads invoice_lines/bill_lines
     * directly rather than scanning journal_lines for a designated "tax"
     * account. There's no tenant-level "default VAT account" setting yet
     * (each invoice/bill picks its own tax_payable/tax_receivable
     * account), so there's no single account to reliably scan in the GL.
     * The figures still match what actually posted, since send()/
     * approve() compute tax the same way — this is a reporting
     * convenience, not a second source of truth. Revisit once a Settings
     * module adds one designated VAT account per direction.
     *
     * @return array{
     *     from: string, to: string,
     *     sales_subtotal: float, output_vat: float,
     *     purchases_subtotal: float, input_vat: float,
     *     net_vat_payable: float,
     * }
     */
    public function vatReturn(string $from, string $to): array
    {
        $invoices = $this->invoices->postedBetween($from, $to);
        $bills = $this->bills->postedBetween($from, $to);

        $salesSubtotal = round($invoices->sum(fn ($invoice) => $invoice->subtotal() * (float) $invoice->exchange_rate), 2);
        $outputVat = round($invoices->sum(fn ($invoice) => $invoice->totalTax() * (float) $invoice->exchange_rate), 2);

        $purchasesSubtotal = round($bills->sum(fn ($bill) => $bill->subtotal()), 2);
        $inputVat = round($bills->sum(fn ($bill) => $bill->totalTax()), 2);

        return [
            'from' => $from,
            'to' => $to,
            'sales_subtotal' => $salesSubtotal,
            'output_vat' => $outputVat,
            'purchases_subtotal' => $purchasesSubtotal,
            'input_vat' => $inputVat,
            'net_vat_payable' => round($outputVat - $inputVat, 2),
        ];
    }

    /**
     * How much each client owes, bucketed by how overdue it is — full
     * amount only, no partial payments (invoices are full-payment-only
     * today, see InvoiceService::recordPayment()). Amounts are in base
     * currency at each invoice's *currently booked* rate ({@see
     * Invoice::bookedExchangeRate()}), so a revalued invoice ages at its
     * revalued value, not its original booking.
     *
     * Reads Invoice rows directly rather than scanning journal_lines —
     * same reasoning as vatReturn(): aging buckets need each invoice's own
     * due_date, which journal_lines don't carry.
     *
     * @return array{
     *     as_of: string,
     *     base_currency: string,
     *     rows: array<int, array{id: int, name: string, current: float, days_1_30: float, days_31_60: float, days_61_90: float, days_90_plus: float, total: float}>,
     *     totals: array{current: float, days_1_30: float, days_31_60: float, days_61_90: float, days_90_plus: float, total: float},
     * }
     */
    public function arAging(string $asOf): array
    {
        return $this->buildAging(
            $this->invoices->outstanding(),
            $asOf,
            fn ($invoice) => $invoice->client_id,
            fn ($invoice) => $invoice->client->name,
            fn ($invoice) => $invoice->due_date->toDateString(),
            fn ($invoice) => round($invoice->total() * $invoice->bookedExchangeRate(), 2),
        );
    }

    /**
     * How much is owed to each vendor, bucketed the same way as
     * arAging() — bills are base-currency only, so no rate conversion.
     *
     * @return array{
     *     as_of: string,
     *     base_currency: string,
     *     rows: array<int, array{id: int, name: string, current: float, days_1_30: float, days_31_60: float, days_61_90: float, days_90_plus: float, total: float}>,
     *     totals: array{current: float, days_1_30: float, days_31_60: float, days_61_90: float, days_90_plus: float, total: float},
     * }
     */
    public function apAging(string $asOf): array
    {
        return $this->buildAging(
            $this->bills->outstanding(),
            $asOf,
            fn ($bill) => $bill->vendor_id,
            fn ($bill) => $bill->vendor->name,
            fn ($bill) => $bill->due_date->toDateString(),
            fn ($bill) => $bill->total(),
        );
    }

    /**
     * @param  Collection<int, mixed>  $records
     */
    private function buildAging(
        Collection $records,
        string $asOf,
        Closure $groupId,
        Closure $groupName,
        Closure $dueDate,
        Closure $amount,
    ): array {
        $bucketKeys = ['current', 'days_1_30', 'days_31_60', 'days_61_90', 'days_90_plus'];
        $asOfDate = Carbon::parse($asOf);

        $rows = $records
            ->groupBy($groupId)
            ->map(function ($group, $id) use ($groupName, $dueDate, $amount, $asOfDate, $bucketKeys) {
                $buckets = array_fill_keys($bucketKeys, 0.0);

                foreach ($group as $record) {
                    // diffInDays from due date -> as-of date, signed: positive
                    // means as-of is after the due date, i.e. overdue by that
                    // many days; negative/zero means not yet due.
                    $daysOverdue = Carbon::parse($dueDate($record))->diffInDays($asOfDate, false);
                    $bucket = match (true) {
                        $daysOverdue <= 0 => 'current',
                        $daysOverdue <= 30 => 'days_1_30',
                        $daysOverdue <= 60 => 'days_31_60',
                        $daysOverdue <= 90 => 'days_61_90',
                        default => 'days_90_plus',
                    };
                    $buckets[$bucket] += $amount($record);
                }

                foreach ($buckets as $key => $value) {
                    $buckets[$key] = round($value, 2);
                }

                return [
                    'id' => (int) $id,
                    'name' => $groupName($group->first()),
                    ...$buckets,
                    'total' => round(array_sum($buckets), 2),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        $totals = array_fill_keys([...$bucketKeys, 'total'], 0.0);
        foreach ($rows as $row) {
            foreach ($totals as $key => $value) {
                $totals[$key] = round($value + $row[$key], 2);
            }
        }

        return [
            'as_of' => $asOf,
            'base_currency' => strtoupper(auth()->user()->tenant->base_currency ?? 'EGP'),
            'rows' => $rows,
            'totals' => $totals,
        ];
    }
}
