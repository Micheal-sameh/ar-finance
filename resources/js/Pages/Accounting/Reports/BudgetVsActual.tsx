import { Head, Link, router } from '@inertiajs/react';
import { PiggyBank, TrendingDown, TrendingUp, Wallet } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { BudgetComparisonTable } from '@/Components/finance/BudgetComparisonTable';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { ReportTitle } from '@/Components/finance/ReportTitle';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { ExportButton } from '@/Components/ui/ExportButton';
import { FilterPanel } from '@/Components/ui/FilterPanel';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import { StatCard } from '@/Components/ui/StatCard';
import type { BudgetVsActualReport, CostCenterOption } from '@/types/finance';

interface Props {
    report: BudgetVsActualReport;
    filters: { from: string; to: string; cost_center_id: string | null };
    costCenters: CostCenterOption[];
}

function isoDate(date: Date): string {
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);

    return local.toISOString().slice(0, 10);
}

/** Quick period choices so nobody has to type dates for the usual questions. */
function presets(): { label: string; from: string; to: string }[] {
    const now = new Date();
    const year = now.getFullYear();
    const quarterStartMonth = Math.floor(now.getMonth() / 3) * 3;

    return [
        { label: 'This month', from: isoDate(new Date(year, now.getMonth(), 1)), to: isoDate(new Date(year, now.getMonth() + 1, 0)) },
        { label: 'This quarter', from: isoDate(new Date(year, quarterStartMonth, 1)), to: isoDate(new Date(year, quarterStartMonth + 3, 0)) },
        { label: 'Year to date', from: `${year}-01-01`, to: isoDate(now) },
        { label: 'Full year', from: `${year}-01-01`, to: `${year}-12-31` },
        { label: 'Last year', from: `${year - 1}-01-01`, to: `${year - 1}-12-31` },
    ];
}

export default function BudgetVsActual({ report, filters, costCenters }: Props) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [costCenter, setCostCenter] = useState(filters.cost_center_id ?? '');

    function visit(next: { from: string; to: string; cost_center_id: string }) {
        router.get(route('reports.budget-vs-actual'), { ...next, cost_center_id: next.cost_center_id || undefined }, { preserveState: true });
    }

    function applyFilter(e: FormEvent) {
        e.preventDefault();
        visit({ from, to, cost_center_id: costCenter });
    }

    function applyPreset(preset: { from: string; to: string }) {
        setFrom(preset.from);
        setTo(preset.to);
        visit({ from: preset.from, to: preset.to, cost_center_id: costCenter });
    }

    const budgetYear = Number(filters.from.slice(0, 4));
    const exportParams = { from: filters.from, to: filters.to, cost_center_id: filters.cost_center_id ?? undefined };
    const spentPercent = report.total_expenses.budget > 0 ? Math.round((report.total_expenses.actual / report.total_expenses.budget) * 100) : null;
    const earnedPercent = report.total_revenue.budget > 0 ? Math.round((report.total_revenue.actual / report.total_revenue.budget) * 100) : null;

    return (
        <>
            <Head title="Budget vs Actual" />

            <PageHeader
                title="Budget vs Actual"
                subtitle="How the business is doing against its plan."
                action={
                    <div className="d-flex gap-2">
                        <ExportButton href={route('reports.budget-vs-actual.export', exportParams)} />
                        <Link href={route('budgets.edit', budgetYear)}>
                            <Button variant="outline">Edit {budgetYear} budget</Button>
                        </Link>
                    </div>
                }
            />

            <Card>
                <FilterPanel active>
                    <div className="d-flex flex-wrap gap-2 mb-3">
                        {presets().map((preset) => (
                            <Button
                                key={preset.label}
                                type="button"
                                size="sm"
                                variant={preset.from === filters.from && preset.to === filters.to ? 'primary' : 'outline'}
                                onClick={() => applyPreset(preset)}
                            >
                                {preset.label}
                            </Button>
                        ))}
                    </div>
                    <form onSubmit={applyFilter} className="d-flex flex-wrap align-items-end gap-3">
                        <div style={{ maxWidth: '160px' }}>
                            <Input type="date" label="From" help="The first day to include." value={from} onChange={(e) => setFrom(e.target.value)} />
                        </div>
                        <div style={{ maxWidth: '160px' }}>
                            <Input type="date" label="To" help="The last day to include. Months only partly inside the dates count for the days that are." value={to} onChange={(e) => setTo(e.target.value)} />
                        </div>
                        {costCenters.length > 0 && (
                            <div style={{ minWidth: '200px' }}>
                                <Select
                                    label="Cost center"
                                    help="Look at one cost center only, or the lines that have none."
                                    value={costCenter}
                                    onChange={(e) => setCostCenter(e.target.value)}
                                >
                                    <option value="">All cost centers</option>
                                    {costCenters.map((center) => (
                                        <option key={center.id} value={center.id}>
                                            {center.name}
                                        </option>
                                    ))}
                                    <option value="unassigned">Unassigned</option>
                                </Select>
                            </div>
                        )}
                        <Button type="submit" variant="outline">
                            Apply
                        </Button>
                    </form>
                </FilterPanel>

                {!report.has_budget && (
                    <EmptyState
                        icon={<PiggyBank size={20} />}
                        title="No budget for this period yet"
                        description="Set a monthly budget per account and this page will show how you are doing against it."
                        action={
                            <Link href={route('budgets.edit', budgetYear)}>
                                <Button>Create the {budgetYear} budget</Button>
                            </Link>
                        }
                    />
                )}

                {report.has_budget && (
                    <div className="row g-3 mb-4">
                        <div className="col-md-3 col-6">
                            <StatCard
                                label="Planned profit"
                                value={report.net_profit.budget.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                sub="Budget for the period"
                                icon={<Wallet size={18} />}
                                color="neutral"
                            />
                        </div>
                        <div className="col-md-3 col-6">
                            <StatCard
                                label="Actual profit"
                                value={report.net_profit.actual.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                sub={report.net_profit.variance >= 0 ? 'On or above plan' : 'Below plan'}
                                icon={<Wallet size={18} />}
                                color={report.net_profit.variance >= 0 ? 'success' : 'danger'}
                            />
                        </div>
                        <div className="col-md-3 col-6">
                            <StatCard
                                label="Income reached"
                                value={earnedPercent === null ? '—' : `${earnedPercent}%`}
                                sub="of planned revenue"
                                icon={<TrendingUp size={18} />}
                                color={earnedPercent !== null && earnedPercent < 80 ? 'warning' : 'success'}
                            />
                        </div>
                        <div className="col-md-3 col-6">
                            <StatCard
                                label="Budget spent"
                                value={spentPercent === null ? '—' : `${spentPercent}%`}
                                sub="of planned expenses"
                                icon={<TrendingDown size={18} />}
                                color={spentPercent !== null && spentPercent > 100 ? 'danger' : spentPercent !== null && spentPercent >= 90 ? 'warning' : 'success'}
                            />
                        </div>
                    </div>
                )}

                <ReportTitle title="Budget vs Actual" period={`For the period ${report.from} – ${report.to}`} />

                <p style={{ fontSize: '13px', color: 'var(--af-label)', marginBottom: '16px' }}>
                    The difference is <span style={{ color: 'var(--af-success)', fontWeight: 600 }}>green</span> when you are better than planned
                    (more income, or less spending) and <span style={{ color: 'var(--af-danger)', fontWeight: 600 }}>red</span> when you are worse.
                </p>

                <BudgetComparisonTable
                    title="Revenue"
                    kind="revenue"
                    rows={report.revenue.map((row) => ({ key: row.account_id, label: `${row.code} · ${row.name}`, actual: row.actual, budget: row.budget, variance: row.variance, accountId: row.account_id }))}
                    totalLabel="Total revenue"
                    total={report.total_revenue}
                    emptyLabel="No revenue budgeted or earned in this period."
                    from={report.from}
                    to={report.to}
                />

                <BudgetComparisonTable
                    title="Expenses"
                    kind="expense"
                    rows={report.expenses.map((row) => ({ key: row.account_id, label: `${row.code} · ${row.name}`, actual: row.actual, budget: row.budget, variance: row.variance, accountId: row.account_id }))}
                    totalLabel="Total expenses"
                    total={report.total_expenses}
                    emptyLabel="No expenses budgeted or spent in this period."
                    from={report.from}
                    to={report.to}
                />

                <div className="d-flex justify-content-between pt-3" style={{ borderTop: '2px solid var(--af-navy)', fontSize: '16px', fontWeight: 700 }}>
                    <span>Net profit</span>
                    <span className="d-flex gap-4">
                        <span style={{ color: 'var(--af-label)', fontWeight: 500 }}>
                            Budget <MoneyDisplay amount={report.net_profit.budget} />
                        </span>
                        <span style={{ color: report.net_profit.actual < 0 ? 'var(--af-danger)' : 'var(--af-text)' }}>
                            Actual <MoneyDisplay amount={report.net_profit.actual} />
                        </span>
                    </span>
                </div>
            </Card>
        </>
    );
}
