import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { ProfitLossGroupedSection } from '@/Components/finance/ProfitLossGroupedSection';
import { ProfitLossSection } from '@/Components/finance/ProfitLossSection';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { ExportButton } from '@/Components/ui/ExportButton';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import type { BalanceSheetGroupBy, BalanceSheetGroupedReport, BalanceSheetReport } from '@/types/finance';

interface Props {
    report: BalanceSheetReport | BalanceSheetGroupedReport;
    filters: { as_of: string; from: string; to: string; group_by: BalanceSheetGroupBy | null };
}

function isGrouped(report: BalanceSheetReport | BalanceSheetGroupedReport): report is BalanceSheetGroupedReport {
    return 'columns' in report;
}

export default function BalanceSheet({ report, filters }: Props) {
    const [asOf, setAsOf] = useState(filters.as_of);
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [groupBy, setGroupBy] = useState<BalanceSheetGroupBy | 'none'>(filters.group_by ?? 'none');

    function applyFilter(e: FormEvent) {
        e.preventDefault();
        router.get(
            route('reports.balance-sheet'),
            {
                as_of: asOf,
                from,
                to,
                group_by: groupBy === 'none' ? null : groupBy,
            },
            { preserveState: true },
        );
    }

    const grouped = isGrouped(report);

    return (
        <>
            <Head title="Balance Sheet" />

            <PageHeader
                title="Balance Sheet"
                subtitle="Assets, liabilities, and equity as of a date — computed from posted journal lines."
                action={
                    <div className="d-flex align-items-center gap-2">
                        <Badge variant={report.is_balanced ? 'success' : 'danger'}>
                            {report.is_balanced ? 'Balanced' : 'Out of balance'}
                        </Badge>
                        <ExportButton label="Download PDF" href={route('reports.balance-sheet.pdf', filters)} target="_blank" />
                    </div>
                }
            />

            <Card>
                <form onSubmit={applyFilter} className="af-filter-bar mb-4">
                    <div style={{ maxWidth: '160px' }}>
                        <Select label="Group by" value={groupBy} onChange={(e) => setGroupBy(e.target.value as BalanceSheetGroupBy | 'none')}>
                            <option value="none">None</option>
                            <option value="month">Monthly</option>
                            <option value="quarter">Quarterly</option>
                        </Select>
                    </div>
                    {groupBy === 'none' ? (
                        <div style={{ maxWidth: '180px' }}>
                            <Input type="date" label="As of" value={asOf} onChange={(e) => setAsOf(e.target.value)} />
                        </div>
                    ) : (
                        <>
                            <div style={{ maxWidth: '160px' }}>
                                <Input type="date" label="From" value={from} onChange={(e) => setFrom(e.target.value)} />
                            </div>
                            <div style={{ maxWidth: '160px' }}>
                                <Input type="date" label="To" value={to} onChange={(e) => setTo(e.target.value)} />
                            </div>
                        </>
                    )}
                    <Button type="submit" variant="outline">
                        Apply
                    </Button>
                </form>

                {grouped ? (
                    <>
                        <ProfitLossGroupedSection
                            title="Assets"
                            columns={report.columns}
                            rows={report.assets}
                            totalLabel="Total Assets"
                            totals={report.total_assets}
                            emptyLabel="No asset balances in this range."
                        />

                        <ProfitLossGroupedSection
                            title="Liabilities"
                            columns={report.columns}
                            rows={report.liabilities}
                            totalLabel="Total Liabilities"
                            totals={report.total_liabilities}
                            emptyLabel="No liability balances in this range."
                        />

                        <ProfitLossGroupedSection
                            title="Equity"
                            columns={report.columns}
                            rows={report.equity}
                            totalLabel="Total Equity"
                            totals={report.total_equity}
                            emptyLabel="No equity balances in this range."
                        />

                        <div className="pt-3" style={{ borderTop: '2px solid var(--af-navy)' }}>
                            <div className="d-flex" style={{ fontSize: '16px', fontWeight: 700 }}>
                                <div style={{ flex: 1 }}>Liabilities + Equity</div>
                            </div>
                            <div style={{ overflowX: 'auto' }}>
                                <table className="w-100" style={{ fontSize: '14px', borderCollapse: 'collapse' }}>
                                    <tbody>
                                        <tr>
                                            <td style={{ padding: '4px 0' }} />
                                            {report.columns.map((column) => (
                                                <td key={column.key} className="text-end" style={{ padding: '4px 8px', whiteSpace: 'nowrap' }}>
                                                    <MoneyDisplay amount={report.liabilities_plus_equity.amounts[column.key] ?? 0} />
                                                </td>
                                            ))}
                                            <td className="text-end" style={{ padding: '4px 0 4px 8px', whiteSpace: 'nowrap', fontWeight: 700 }}>
                                                <MoneyDisplay amount={report.liabilities_plus_equity.total} />
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
                            title="Assets"
                            rows={report.assets.map((row) => ({ key: row.account_id, label: row.name, amount: row.balance, accountId: row.account_id }))}
                            totalLabel="Total Assets"
                            total={report.total_assets}
                            emptyLabel="No asset balances as of this date."
                            linkTo={report.as_of}
                        />

                        <ProfitLossSection
                            title="Liabilities"
                            rows={report.liabilities.map((row) => ({ key: row.account_id, label: row.name, amount: row.balance, accountId: row.account_id }))}
                            totalLabel="Total Liabilities"
                            total={report.total_liabilities}
                            emptyLabel="No liability balances as of this date."
                            linkTo={report.as_of}
                        />

                        <ProfitLossSection
                            title="Equity"
                            rows={report.equity.map((row) => ({ key: row.account_id, label: row.name, amount: row.balance, accountId: row.account_id }))}
                            totalLabel="Total Equity"
                            total={report.total_equity}
                            emptyLabel="No equity balances as of this date."
                            linkTo={report.as_of}
                        />

                        <div
                            className="d-flex align-items-center justify-content-between pt-3"
                            style={{ borderTop: '2px solid var(--af-navy)', fontSize: '16px', fontWeight: 700 }}
                        >
                            <span>Liabilities + Equity</span>
                            <MoneyDisplay amount={report.total_liabilities + report.total_equity} />
                        </div>
                    </>
                )}
            </Card>
        </>
    );
}
