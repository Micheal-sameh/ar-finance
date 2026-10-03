import { AmountLink } from './AmountLink';
import { MoneyDisplay } from './MoneyDisplay';
import type { ProfitLossGroupedColumn, ProfitLossGroupedRow, ProfitLossGroupedTotals } from '@/types/finance';

export interface ProfitLossGroupedSectionProps {
    title: string;
    columns: ProfitLossGroupedColumn[];
    rows: ProfitLossGroupedRow[];
    totalLabel: string;
    totals: ProfitLossGroupedTotals;
    currency?: string;
    emptyLabel?: string;
}

/**
 * Revenue/Expenses broken out across several columns (months, quarters, or
 * cost centers) instead of ProfitLossSection's single current/prior pair —
 * a real table, since the column count is dynamic and fixed flex widths
 * stop working past a handful of columns.
 */
export function ProfitLossGroupedSection({
    title,
    columns,
    rows,
    totalLabel,
    totals,
    currency = 'EGP',
    emptyLabel = 'No activity in this period.',
}: ProfitLossGroupedSectionProps) {
    return (
        <div className="mb-4">
            <div style={{ fontSize: '13px', fontWeight: 600, color: 'var(--af-navy)', marginBottom: '8px' }}>{title}</div>

            <div style={{ overflowX: 'auto' }}>
                <table className="w-100" style={{ fontSize: '13px', borderCollapse: 'collapse' }}>
                    <thead>
                        <tr style={{ fontSize: '11px', color: 'var(--af-label)', textTransform: 'uppercase' }}>
                            <th className="text-start" style={{ fontWeight: 500, padding: '4px 8px 4px 0' }} />
                            {columns.map((column) => (
                                <th key={column.key} className="text-end" style={{ fontWeight: 500, padding: '4px 8px', whiteSpace: 'nowrap' }}>
                                    {column.label}
                                </th>
                            ))}
                            <th className="text-end" style={{ fontWeight: 500, padding: '4px 0 4px 8px' }}>
                                Total
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 ? (
                            <tr>
                                <td colSpan={columns.length + 2} style={{ color: 'var(--af-label)', padding: '6px 0' }}>
                                    {emptyLabel}
                                </td>
                            </tr>
                        ) : (
                            rows.map((row) => (
                                <tr key={row.account_id}>
                                    <td style={{ padding: '4px 8px 4px 0' }}>{row.name}</td>
                                    {columns.map((column) => (
                                        <td key={column.key} className="text-end" style={{ padding: '4px 8px', whiteSpace: 'nowrap' }}>
                                            <AmountLink
                                                accountId={row.account_id}
                                                from={column.from}
                                                to={column.to}
                                                costCenterId={column.cost_center_id}
                                                amount={row.amounts[column.key] ?? 0}
                                                currency={currency}
                                            />
                                        </td>
                                    ))}
                                    <td className="text-end" style={{ padding: '4px 0 4px 8px', whiteSpace: 'nowrap' }}>
                                        <MoneyDisplay amount={row.total} currency={currency} />
                                    </td>
                                </tr>
                            ))
                        )}
                        <tr style={{ fontWeight: 600, borderTop: '1px solid var(--af-border)' }}>
                            <td style={{ padding: '8px 8px 8px 0' }}>{totalLabel}</td>
                            {columns.map((column) => (
                                <td key={column.key} className="text-end" style={{ padding: '8px', whiteSpace: 'nowrap' }}>
                                    <MoneyDisplay amount={totals.amounts[column.key] ?? 0} currency={currency} />
                                </td>
                            ))}
                            <td className="text-end" style={{ padding: '8px 0 8px 8px', whiteSpace: 'nowrap' }}>
                                <MoneyDisplay amount={totals.total} currency={currency} />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    );
}
