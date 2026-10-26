import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Icon, type IconName } from '@/Components/layout/Icon';
import { PageHeader } from '@/Components/layout/PageHeader';
import type { BudgetSummary } from '@/types/finance';
import { budgetHealth } from '@/utils/finance';

interface TrendPoint {
    month: string;
    revenue: number;
    expenses: number;
}

interface ActivityRow {
    id: number;
    date: string;
    description: string;
    reference: string | null;
    source_label: string;
    amount: number;
}

interface ExpenseSlice {
    label: string;
    amount: number;
    percent: number;
}

interface OverdueRow {
    id: number;
    name: string;
    amount: number;
    age_label: string;
}

interface Summary {
    cash: number;
    receivables: number;
    payables: number;
    revenue_month: number;
    expenses_month: number;
    net_profit_month: number;
    period: { from: string; to: string };
    previous: { revenue: number; expenses: number; net_profit: number };
    trend: TrendPoint[];
    expense_breakdown: ExpenseSlice[];
    overdue: { total: number; count: number; rows: OverdueRow[] };
    recent_activity: ActivityRow[];
    budget?: BudgetSummary;
}

interface Props {
    summary: Summary;
    baseCurrency: string;
}

const sliceColors = ['blue', 'gold', 'purple', 'green', 'gray'] as const;
const sliceHex: Record<(typeof sliceColors)[number], string> = {
    blue: 'var(--blue)',
    gold: 'var(--gold)',
    purple: 'var(--purple)',
    green: 'var(--green)',
    gray: '#adb6c4',
};

function formatMoney(value: number, currency: string): string {
    return `${currency} ${value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatCompact(value: number): string {
    if (value >= 1_000_000) {
        return `${+(value / 1_000_000).toFixed(2)}M`;
    }
    if (value >= 1_000) {
        return `${+(value / 1_000).toFixed(0)}K`;
    }

    return `${Math.round(value)}`;
}

function initialsOf(text: string): string {
    return text
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => Array.from(word)[0]?.toUpperCase() ?? '')
        .join('');
}

/** Smooth path through the series, scaled into the sparkline's 120x44 viewBox. */
function sparklinePath(series: number[]): string | null {
    if (series.length < 2) {
        return null;
    }

    const min = Math.min(...series);
    const max = Math.max(...series);
    const range = max - min || 1;
    const stepX = 120 / (series.length - 1);
    const points = series.map((value, i) => ({ x: i * stepX, y: 40 - ((value - min) / range) * 34 }));

    return points.reduce((path, point, i) => {
        if (i === 0) {
            return `M${point.x.toFixed(1)} ${point.y.toFixed(1)}`;
        }
        const previous = points[i - 1];
        const midX = ((previous.x + point.x) / 2).toFixed(1);

        return `${path} C${midX} ${previous.y.toFixed(1)} ${midX} ${point.y.toFixed(1)} ${point.x.toFixed(1)} ${point.y.toFixed(1)}`;
    }, '');
}

function Sparkline({ color, series, id }: { color: string; series: number[]; id: string }) {
    const path = sparklinePath(series);

    if (!path) {
        return null;
    }

    return (
        <svg className="spark" viewBox="0 0 120 44" preserveAspectRatio="none">
            <defs>
                <linearGradient id={`g-${id}`} x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stopColor={color} stopOpacity=".22" />
                    <stop offset="1" stopColor={color} stopOpacity="0" />
                </linearGradient>
            </defs>
            <path d={`${path} L120 44 L0 44 Z`} fill={`url(#g-${id})`} />
            <path d={path} fill="none" stroke={color} strokeWidth="2.2" vectorEffect="non-scaling-stroke" />
        </svg>
    );
}

/** Percent change against the previous period, or null when there is nothing to compare. */
function percentChange(current: number, previous: number | null): number | null {
    return previous === null || previous === 0 ? null : ((current - previous) / Math.abs(previous)) * 100;
}

interface Kpi {
    title: string;
    amount: number;
    icon: IconName;
    tone: 'blue' | 'amber' | 'gold' | 'green';
    color: string;
    series: number[];
    previous: number | null;
    note: string;
    lowerIsBetter?: boolean;
}

function KpiCard({ kpi, currency }: { kpi: Kpi; currency: string }) {
    const change = percentChange(kpi.amount, kpi.previous);
    const favorable = change === null ? true : kpi.lowerIsBetter ? change <= 0 : change >= 0;

    return (
        <article className="kpi-card">
            <div className="kpi-top">
                <div className={`kpi-icon ${kpi.tone}`}>
                    <Icon name={kpi.icon} />
                </div>
                {change !== null && (
                    <span className={`trend ${favorable ? 'up' : 'down'}`}>
                        <Icon name={change >= 0 ? 'arrowUp' : 'arrowDown'} size={13} />
                        {Math.abs(change).toFixed(1)}%
                    </span>
                )}
            </div>
            <span className="card-label">{kpi.title}</span>
            <strong className="kpi-value">{formatMoney(kpi.amount, currency)}</strong>
            <div className="kpi-foot">
                <small>{kpi.note}</small>
                <Sparkline color={kpi.color} series={kpi.series} id={kpi.tone} />
            </div>
        </article>
    );
}

function IncomeVsExpenses({ trend }: { trend: TrendPoint[] }) {
    const peak = Math.max(1, ...trend.flatMap((point) => [point.revenue, point.expenses]));
    const magnitude = 10 ** Math.floor(Math.log10(peak));
    const axisMax = Math.ceil(peak / (magnitude / 2)) * (magnitude / 2);
    const ticks = [1, 0.75, 0.5, 0.25, 0].map((fraction) => axisMax * fraction);

    return (
        <article className="card income-card">
            <div className="card-head">
                <div>
                    <h2>Income vs. expenses</h2>
                    <p>Monthly performance for the last {trend.length} months</p>
                </div>
                <div className="legend">
                    <span>
                        <i className="blue" />
                        Income
                    </span>
                    <span>
                        <i className="gold" />
                        Expenses
                    </span>
                </div>
            </div>
            <div className="chart-body">
                <div className="y-labels">
                    {ticks.map((tick) => (
                        <span key={tick}>{tick === 0 ? '0' : formatCompact(tick)}</span>
                    ))}
                </div>
                <div className="bar-chart" style={{ gridTemplateColumns: `repeat(${trend.length}, 1fr)` }}>
                    {trend.map((point) => (
                        <div className="month" key={point.month}>
                            <div className="bars">
                                <i style={{ height: `${(point.revenue / axisMax) * 100}%` }} />
                                <i style={{ height: `${(point.expenses / axisMax) * 100}%` }} />
                            </div>
                            <span>{point.month.split(' ')[0]}</span>
                        </div>
                    ))}
                </div>
            </div>
        </article>
    );
}

function ExpenseBreakdown({
    slices,
    total,
    currency,
    period,
}: {
    slices: ExpenseSlice[];
    total: number;
    currency: string;
    period: string;
}) {
    let cursor = 0;
    const stops = slices.map((slice, i) => {
        const start = cursor;
        cursor += slice.percent;

        return `${sliceHex[sliceColors[i]]} ${start}% ${i === slices.length - 1 ? 100 : cursor}%`;
    });

    return (
        <article className="card expense-card">
            <div className="card-head">
                <div>
                    <h2>Expense breakdown</h2>
                    <p>{period}</p>
                </div>
            </div>
            <div className="donut-wrap">
                <div className="donut" style={slices.length ? { background: `conic-gradient(${stops.join(',')})` } : undefined}>
                    <div>
                        <b>
                            {currency} {formatCompact(total)}
                        </b>
                        <span>Total expenses</span>
                    </div>
                </div>
            </div>
            <div className="expense-legend">
                {slices.map((slice, i) => (
                    <div key={slice.label}>
                        <span>
                            <i className={sliceColors[i]} />
                            {slice.label}
                        </span>
                        <b>{Math.round(slice.percent)}%</b>
                    </div>
                ))}
            </div>
        </article>
    );
}

export default function DashboardIndex({ summary, baseCurrency }: Props) {
    const revenueSeries = summary.trend.map((point) => point.revenue);
    const expenseSeries = summary.trend.map((point) => point.expenses);
    const profitSeries = summary.trend.map((point) => point.revenue - point.expenses);

    const kpis: Kpi[] = [
        {
            title: 'Total revenue',
            amount: summary.revenue_month,
            icon: 'profit',
            tone: 'blue',
            color: '#52758A',
            series: revenueSeries,
            previous: summary.previous.revenue,
            note: 'vs. previous period',
        },
        {
            title: 'Total expenses',
            amount: summary.expenses_month,
            icon: 'receipt',
            tone: 'amber',
            color: '#9B7848',
            series: expenseSeries,
            previous: summary.previous.expenses,
            note: 'vs. previous period',
            lowerIsBetter: true,
        },
        {
            title: 'Net profit',
            amount: summary.net_profit_month,
            icon: 'wallet',
            tone: 'gold',
            color: '#9B8358',
            series: profitSeries,
            previous: summary.previous.net_profit,
            note: 'vs. previous period',
        },
        {
            title: 'Cash balance',
            amount: summary.cash,
            icon: 'bank',
            tone: 'green',
            color: '#527864',
            series: [],
            previous: null,
            note: 'across bank accounts',
        },
    ];

    const [from, setFrom] = useState(summary.period.from);
    const [to, setTo] = useState(summary.period.to);

    function applyPeriod(nextFrom: string, nextTo: string) {
        if (!nextFrom || !nextTo || nextTo < nextFrom) {
            return;
        }
        router.get(route('dashboard'), { from: nextFrom, to: nextTo }, { preserveState: true, preserveScroll: true });
    }

    function applyPreset(preset: string) {
        const today = new Date();
        const iso = (date: Date) => date.toLocaleDateString('en-CA');
        const year = today.getFullYear();
        const month = today.getMonth();
        const ranges: Record<string, [Date, Date]> = {
            month: [new Date(year, month, 1), today],
            lastMonth: [new Date(year, month - 1, 1), new Date(year, month, 0)],
            quarter: [new Date(year, Math.floor(month / 3) * 3, 1), today],
            year: [new Date(year, 0, 1), today],
        };
        const range = ranges[preset];

        if (range) {
            setFrom(iso(range[0]));
            setTo(iso(range[1]));
            applyPeriod(iso(range[0]), iso(range[1]));
        }
    }

    return (
        <>
            <Head title="Dashboard" />

            <PageHeader title="Dashboard" subtitle="Here’s how your organization is performing." />

            <div className="filters-row">
                <select
                    className="select-button"
                    aria-label="Period"
                    value=""
                    onChange={(event) => applyPreset(event.target.value)}
                >
                    <option value="" disabled>
                        Quick period…
                    </option>
                    <option value="month">This month</option>
                    <option value="lastMonth">Last month</option>
                    <option value="quarter">This quarter</option>
                    <option value="year">This year</option>
                </select>
                <input
                    type="date"
                    className="select-button"
                    aria-label="From"
                    value={from}
                    onChange={(event) => {
                        setFrom(event.target.value);
                        applyPeriod(event.target.value, to);
                    }}
                />
                <input
                    type="date"
                    className="select-button"
                    aria-label="To"
                    value={to}
                    onChange={(event) => {
                        setTo(event.target.value);
                        applyPeriod(from, event.target.value);
                    }}
                />
            </div>

            <section className="kpi-grid">
                {kpis.map((kpi) => (
                    <KpiCard key={kpi.title} kpi={kpi} currency={baseCurrency} />
                ))}
            </section>

            <section className="chart-grid">
                <IncomeVsExpenses trend={summary.trend} />
                <ExpenseBreakdown
                    slices={summary.expense_breakdown}
                    total={summary.expenses_month}
                    currency={baseCurrency}
                    period={`${summary.period.from} – ${summary.period.to}`}
                />
            </section>

            <section className="bottom-grid">
                <article className="card transactions">
                    <div className="card-head">
                        <div>
                            <h2>Recent transactions</h2>
                            <p>Latest posted activity</p>
                        </div>
                        <Link href={route('journals.index')} className="text-button">
                            View all <Icon name="chevron" size={14} />
                        </Link>
                    </div>
                    <div className="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>TRANSACTION</th>
                                    <th>TYPE</th>
                                    <th>DATE</th>
                                    <th className="align-right">AMOUNT</th>
                                    <th>STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                {summary.recent_activity.length === 0 && (
                                    <tr>
                                        <td colSpan={5}>No activity yet. Posted transactions will show up here.</td>
                                    </tr>
                                )}
                                {summary.recent_activity.map((entry) => (
                                    <tr
                                        key={entry.id}
                                        style={{ cursor: 'pointer' }}
                                        onClick={() => router.get(route('journals.show', entry.id))}
                                    >
                                        <td>
                                            <div className="transaction-name">
                                                <div className="mini-avatar">{initialsOf(entry.description)}</div>
                                                <div>
                                                    <b>{entry.description}</b>
                                                    <span>{entry.reference ?? `JE-${entry.id}`}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{entry.source_label}</td>
                                        <td>
                                            {new Date(entry.date).toLocaleDateString('en-GB', {
                                                day: 'numeric',
                                                month: 'short',
                                                year: 'numeric',
                                            })}
                                        </td>
                                        <td className="align-right">
                                            <span className="money">{formatMoney(entry.amount, baseCurrency)}</span>
                                        </td>
                                        <td>
                                            <span className="badge success">
                                                <i />
                                                Posted
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </article>

                <article className="card overdue">
                    <div className="card-head">
                        <div>
                            <h2>Overdue invoices</h2>
                            <p>{formatMoney(summary.overdue.total, baseCurrency)} outstanding</p>
                        </div>
                        {summary.overdue.count > 0 && <span className="badge error">{summary.overdue.count} overdue</span>}
                    </div>
                    {summary.overdue.rows.length === 0 && (
                        <div className="overdue-row">
                            <div>
                                <b>Nothing overdue</b>
                                <span>All receivables are within terms.</span>
                            </div>
                        </div>
                    )}
                    {summary.overdue.rows.map((row, i) => (
                        <div className="overdue-row" key={row.id}>
                            <div className={`company-avatar av${i}`}>{initialsOf(row.name)}</div>
                            <div>
                                <b>{row.name}</b>
                                <span>{row.age_label} overdue</span>
                            </div>
                            <strong>{formatMoney(row.amount, baseCurrency)}</strong>
                        </div>
                    ))}
                    <Link href={route('invoices.index')} className="button secondary full">
                        Review overdue invoices
                    </Link>
                </article>
            </section>

            {summary.budget?.has_budget && (
                <article className="card" style={{ marginTop: '16px' }}>
                    <div className="card-head">
                        <div>
                            <h2>Budget</h2>
                            <p>How this period compares with plan</p>
                        </div>
                        <Link href={route('reports.budget-vs-actual', { from: summary.period.from, to: summary.period.to })}>Open report</Link>
                    </div>
                    {[
                        { label: 'Revenue', kind: 'revenue' as const, ...summary.budget.revenue },
                        { label: 'Expenses', kind: 'expense' as const, ...summary.budget.expenses },
                    ].map((row) => {
                        const health = budgetHealth(row.kind, row.budget, row.actual);
                        const percent = row.budget > 0 ? Math.round((row.actual / row.budget) * 100) : null;
                        const color = health === 'bad' ? 'var(--af-danger)' : health === 'warn' ? 'var(--af-warning)' : 'var(--af-success)';

                        return (
                            <div key={row.label} style={{ marginBottom: '14px' }}>
                                <div className="d-flex justify-content-between" style={{ fontSize: '13.5px', marginBottom: '6px' }}>
                                    <b>{row.label}</b>
                                    <span>
                                        {formatMoney(row.actual, baseCurrency)} of {formatMoney(row.budget, baseCurrency)}
                                        {percent !== null && <b style={{ color, marginLeft: '8px' }}>{percent}%</b>}
                                    </span>
                                </div>
                                <div style={{ height: '8px', borderRadius: '999px', backgroundColor: '#EEF1F6', overflow: 'hidden' }}>
                                    <div style={{ height: '100%', width: `${Math.min(100, percent ?? 0)}%`, backgroundColor: color, borderRadius: '999px' }} />
                                </div>
                            </div>
                        );
                    })}
                </article>
            )}
        </>
    );
}
