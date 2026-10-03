import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { ProfitLossGroupedSection } from '@/Components/finance/ProfitLossGroupedSection';
import { ProfitLossSection } from '@/Components/finance/ProfitLossSection';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { ExportMenu } from '@/Components/ui/ExportMenu';
import { FilterPanel } from '@/Components/ui/FilterPanel';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import type { ProfitAndLossGroupBy, ProfitAndLossGroupedReport, ProfitAndLossReport } from '@/types/finance';

interface Props {
    report: ProfitAndLossReport | ProfitAndLossGroupedReport;
    filters: {
        from: string;
        to: string;
        compare_from: string | null;
        compare_to: string | null;
        group_by: ProfitAndLossGroupBy | null;
    };
}

function isGrouped(report: ProfitAndLossReport | ProfitAndLossGroupedReport): report is ProfitAndLossGroupedReport {
    return 'columns' in report;
}

export default function ProfitAndLoss({ report, filters }: Props) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [groupBy, setGroupBy] = useState<ProfitAndLossGroupBy | 'none'>(filters.group_by ?? 'none');
    const [compare, setCompare] = useState(Boolean(filters.compare_from));
    const [compareFrom, setCompareFrom] = useState(filters.compare_from ?? '');
    const [compareTo, setCompareTo] = useState(filters.compare_to ?? '');

    function applyFilter(e: FormEvent) {
        e.preventDefault();
        router.get(
            route('reports.profit-and-loss'),
            {
                from,
                to,
                group_by: groupBy === 'none' ? null : groupBy,
                compare_from: groupBy === 'none' && compare ? compareFrom : null,
                compare_to: groupBy === 'none' && compare ? compareTo : null,
            },
            { preserveState: true },
        );
    }

    const grouped = isGrouped(report);
    const showSecondary = !grouped && Boolean(report.compare_from);
    const secondaryLabel = showSecondary && !grouped ? `${report.compare_from} – ${report.compare_to}` : undefined;

    return (
        <>
            <Head title="Profit & Loss" />

            <PageHeader
                title="Profit & Loss"
                subtitle="Revenue and expenses, computed from posted journal lines."
                action={
                    <ExportMenu
                        options={[
                            { label: 'Export to Excel', href: route('reports.profit-and-loss.export', filters) },
                            { label: 'Download PDF', href: route('reports.profit-and-loss.pdf', filters), target: '_blank' },
                        ]}
                    />
                }
            />

            <Card>
                <FilterPanel active>
                    <form onSubmit={applyFilter} className="af-filter-bar mb-4">
                        <div style={{ maxWidth: '160px' }}>
                            <Input type="date" label="From" value={from} onChange={(e) => setFrom(e.target.value)} />
                        </div>
                        <div style={{ maxWidth: '160px' }}>
                            <Input type="date" label="To" value={to} onChange={(e) => setTo(e.target.value)} />
                        </div>
                        <div style={{ maxWidth: '160px' }}>
                            <Select
                                label="Group by"
                                value={groupBy}
                                onChange={(e) => setGroupBy(e.target.value as ProfitAndLossGroupBy | 'none')}
                            >
                                <option value="none">None</option>
                                <option value="month">Monthly</option>
                                <option value="quarter">Quarterly</option>
                                <option value="cost_center">Cost centers</option>
                            </Select>
                        </div>
                        {groupBy === 'none' && (
                            <>
                                <div className="form-check ms-2 mb-2">
                                    <input
                                        className="form-check-input"
                                        type="checkbox"
                                        id="compare"
                                        checked={compare}
                                        onChange={(e) => setCompare(e.target.checked)}
                                    />
                                    <label className="form-check-label" htmlFor="compare" style={{ fontSize: '13px' }}>
                                        Compare to another period
                                    </label>
                                </div>
                                {compare && (
                                    <>
                                        <div style={{ maxWidth: '160px' }}>
                                            <Input
                                                type="date"
                                                label="Compare from"
                                                value={compareFrom}
                                                onChange={(e) => setCompareFrom(e.target.value)}
                                            />
                                        </div>
                                        <div style={{ maxWidth: '160px' }}>
                                            <Input type="date" label="Compare to" value={compareTo} onChange={(e) => setCompareTo(e.target.value)} />
                                        </div>
                                    </>
                                )}
                            </>
                        )}
                        <Button type="submit" variant="outline">
                            Apply
                        </Button>
                    </form>
                </FilterPanel>

                {grouped ? (
                    <>
                        <ProfitLossGroupedSection
                            title="Revenue"
                            columns={report.columns}
                            rows={report.revenue}
                            totalLabel="Total Revenue"
                            totals={report.total_revenue}
                            emptyLabel="No revenue posted in this period."
                        />

                        <ProfitLossGroupedSection
                            title="Expenses"
                            columns={report.columns}
                            rows={report.expenses}
                            totalLabel="Total Expenses"
                            totals={report.total_expenses}
                            emptyLabel="No expenses posted in this period."
                        />

                        <div className="pt-3" style={{ borderTop: '2px solid var(--af-navy)' }}>
                            <div className="d-flex" style={{ fontSize: '16px', fontWeight: 700 }}>
                                <div style={{ flex: 1 }}>Net Profit</div>
                            </div>
                            <div style={{ overflowX: 'auto' }}>
                                <table className="w-100" style={{ fontSize: '14px', borderCollapse: 'collapse' }}>
                                    <tbody>
                                        <tr>
                                            <td style={{ padding: '4px 0' }} />
                                            {report.columns.map((column) => (
                                                <td key={column.key} className="text-end" style={{ padding: '4px 8px', whiteSpace: 'nowrap' }}>
                                                    <span
                                                        style={{
                                                            color:
                                                                (report.net_profit.amounts[column.key] ?? 0) >= 0
                                                                    ? 'var(--af-success)'
                                                                    : 'var(--af-danger)',
                                                        }}
                                                    >
                                                        <MoneyDisplay amount={report.net_profit.amounts[column.key] ?? 0} />
                                                    </span>
                                                </td>
                                            ))}
                                            <td className="text-end" style={{ padding: '4px 0 4px 8px', whiteSpace: 'nowrap', fontWeight: 700 }}>
                                                <span
                                                    style={{ color: report.net_profit.total >= 0 ? 'var(--af-success)' : 'var(--af-danger)' }}
                                                >
                                                    <MoneyDisplay amount={report.net_profit.total} />
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </>
                ) : (
                    <>
                        <ProfitLossSection
                            title="Revenue"
                            rows={report.revenue.map((row) => ({
                                key: row.account_id,
                                label: row.name,
                                amount: row.current,
                                secondaryAmount: row.prior,
                                accountId: row.account_id,
                            }))}
                            totalLabel="Total Revenue"
                            total={report.total_revenue.current}
                            secondaryTotal={report.total_revenue.prior}
                            secondaryColumnLabel={secondaryLabel}
                            emptyLabel="No revenue posted in this period."
                            linkFrom={report.from}
                            linkTo={report.to}
                            secondaryLinkFrom={report.compare_from}
                            secondaryLinkTo={report.compare_to}
                        />

                        <ProfitLossSection
                            title="Expenses"
                            rows={report.expenses.map((row) => ({
                                key: row.account_id,
                                label: row.name,
                                amount: row.current,
                                secondaryAmount: row.prior,
                                accountId: row.account_id,
                            }))}
                            totalLabel="Total Expenses"
                            total={report.total_expenses.current}
                            secondaryTotal={report.total_expenses.prior}
                            secondaryColumnLabel={secondaryLabel}
                            emptyLabel="No expenses posted in this period."
                            linkFrom={report.from}
                            linkTo={report.to}
                            secondaryLinkFrom={report.compare_from}
                            secondaryLinkTo={report.compare_to}
                        />

                        <div
                            className="d-flex align-items-center justify-content-between pt-3"
                            style={{ borderTop: '2px solid var(--af-navy)', fontSize: '16px', fontWeight: 700 }}
                        >
                            <span>Net Profit</span>
                            <span style={{ color: report.net_profit.current >= 0 ? 'var(--af-success)' : 'var(--af-danger)' }}>
                                <MoneyDisplay amount={report.net_profit.current} />
                            </span>
                        </div>
                    </>
                )}
            </Card>
        </>
    );
}
