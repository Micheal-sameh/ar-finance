import { Link } from '@inertiajs/react';
import { Badge } from '@/Components/ui/Badge';
import type { Variant } from '@/theme';
import { type BudgetKind, budgetHealth, budgetHealthLabel } from '@/utils/finance';
import { MoneyDisplay } from './MoneyDisplay';

export interface BudgetComparisonTableRow {
    key: string | number;
    label: string;
    actual: number;
    budget: number;
    /** Favourable when positive: more income than planned, or less spending than planned. */
    variance: number;
    accountId?: number;
}

interface BudgetComparisonTableProps {
    title: string;
    kind: BudgetKind;
    rows: BudgetComparisonTableRow[];
    totalLabel: string;
    total: { actual: number; budget: number; variance: number };
    emptyLabel: string;
    /** Date range for the drill-down link from an amount to the General Ledger. */
    from?: string;
    to?: string;
}

const HEALTH_VARIANT: Record<'good' | 'warn' | 'bad' | 'none', Variant> = {
    good: 'success',
    warn: 'warning',
    bad: 'danger',
    none: 'neutral',
};

const HEALTH_COLOR: Record<'good' | 'warn' | 'bad' | 'none', string> = {
    good: 'var(--af-success)',
    warn: 'var(--af-warning)',
    bad: 'var(--af-danger)',
    none: 'var(--af-border)',
};

function UsedBar({ kind, budget, actual }: { kind: BudgetKind; budget: number; actual: number }) {
    const health = budgetHealth(kind, budget, actual);

    if (health === 'none') {
        return <span style={{ color: 'var(--af-label)', fontSize: '12px' }}>—</span>;
    }

    const percent = (actual / budget) * 100;

    return (
        <div style={{ minWidth: '120px' }} title={`${Math.round(percent)}% of budget`}>
            <div className="d-flex justify-content-between mb-1" style={{ fontSize: '12px' }}>
                <span style={{ color: HEALTH_COLOR[health] === 'var(--af-border)' ? 'var(--af-label)' : HEALTH_COLOR[health], fontWeight: 600 }}>
                    {Math.round(percent)}%
                </span>
            </div>
            <div style={{ height: '6px', borderRadius: '999px', backgroundColor: '#EEF1F6', overflow: 'hidden' }}>
                <div
                    style={{
                        height: '100%',
                        width: `${Math.min(100, Math.max(0, percent))}%`,
                        backgroundColor: HEALTH_COLOR[health],
                        borderRadius: '999px',
                    }}
                />
            </div>
        </div>
    );
}

function Variance({ value }: { value: number }) {
    if (value === 0) {
        return <span style={{ color: 'var(--af-label)' }}>0.00</span>;
    }

    return (
        <span style={{ color: value > 0 ? 'var(--af-success)' : 'var(--af-danger)', fontWeight: 600 }}>
            {value > 0 ? '+' : '−'}
            <MoneyDisplay amount={Math.abs(value)} />
        </span>
    );
}

/**
 * Budget against actual for one section (revenue or expenses): what was
 * planned, what happened, the gap, and a coloured bar showing how much of
 * the budget is used. Shared by the Budget vs Actual report and the P&L.
 */
export function BudgetComparisonTable({ title, kind, rows, totalLabel, total, emptyLabel, from, to }: BudgetComparisonTableProps) {
    const totalHealth = budgetHealth(kind, total.budget, total.actual);

    return (
        <div className="mb-4">
            <h3 style={{ fontSize: '15px', fontWeight: 600, color: 'var(--af-navy)', marginBottom: '8px' }}>{title}</h3>

            {rows.length === 0 ? (
                <div style={{ color: 'var(--af-label)', fontSize: '14px', padding: '8px 0' }}>{emptyLabel}</div>
            ) : (
                <div style={{ overflowX: 'auto' }}>
                    <table className="w-100" style={{ fontSize: '14px', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr style={{ color: 'var(--af-label)', fontSize: '12px', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                                <th style={{ padding: '6px 8px 6px 0', fontWeight: 600 }}>Account</th>
                                <th className="text-end" style={{ padding: '6px 8px', fontWeight: 600 }}>Budget</th>
                                <th className="text-end" style={{ padding: '6px 8px', fontWeight: 600 }}>Actual</th>
                                <th className="text-end" style={{ padding: '6px 8px', fontWeight: 600 }}>Difference</th>
                                <th style={{ padding: '6px 0 6px 12px', fontWeight: 600, width: '170px' }}>{kind === 'expense' ? 'Budget used' : 'Target reached'}</th>
                                <th style={{ padding: '6px 0 6px 8px', fontWeight: 600 }}>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => {
                                const health = budgetHealth(kind, row.budget, row.actual);

                                return (
                                    <tr key={row.key} style={{ borderTop: '1px solid var(--af-border)' }}>
                                        <td style={{ padding: '8px 8px 8px 0' }}>{row.label}</td>
                                        <td className="text-end" style={{ padding: '8px' }}>
                                            <MoneyDisplay amount={row.budget} />
                                        </td>
                                        <td className="text-end" style={{ padding: '8px' }}>
                                            {row.accountId && from && to ? (
                                                <Link href={route('reports.general-ledger', { account_id: row.accountId, from, to })}>
                                                    <MoneyDisplay amount={row.actual} />
                                                </Link>
                                            ) : (
                                                <MoneyDisplay amount={row.actual} />
                                            )}
                                        </td>
                                        <td className="text-end" style={{ padding: '8px', whiteSpace: 'nowrap' }}>
                                            <Variance value={row.variance} />
                                        </td>
                                        <td style={{ padding: '8px 0 8px 12px' }}>
                                            <UsedBar kind={kind} budget={row.budget} actual={row.actual} />
                                        </td>
                                        <td style={{ padding: '8px 0 8px 8px', whiteSpace: 'nowrap' }}>
                                            <Badge variant={HEALTH_VARIANT[health]}>{row.budget === 0 && row.actual !== 0 ? 'Not budgeted' : budgetHealthLabel(kind, health)}</Badge>
                                        </td>
                                    </tr>
                                );
                            })}
                            <tr style={{ borderTop: '2px solid var(--af-navy)', fontWeight: 700 }}>
                                <td style={{ padding: '10px 8px 10px 0' }}>{totalLabel}</td>
                                <td className="text-end" style={{ padding: '10px 8px' }}>
                                    <MoneyDisplay amount={total.budget} />
                                </td>
                                <td className="text-end" style={{ padding: '10px 8px' }}>
                                    <MoneyDisplay amount={total.actual} />
                                </td>
                                <td className="text-end" style={{ padding: '10px 8px', whiteSpace: 'nowrap' }}>
                                    <Variance value={total.variance} />
                                </td>
                                <td style={{ padding: '10px 0 10px 12px' }}>
                                    <UsedBar kind={kind} budget={total.budget} actual={total.actual} />
                                </td>
                                <td style={{ padding: '10px 0 10px 8px', whiteSpace: 'nowrap' }}>
                                    <Badge variant={HEALTH_VARIANT[totalHealth]}>{budgetHealthLabel(kind, totalHealth)}</Badge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
