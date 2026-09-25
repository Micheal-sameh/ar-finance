import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { ProfitLossSection } from '@/Components/finance/ProfitLossSection';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { ExportMenu } from '@/Components/ui/ExportMenu';
import { Input } from '@/Components/ui/Input';
import type { ProfitAndLossReport } from '@/types/finance';

interface Props {
    report: ProfitAndLossReport;
    filters: { from: string; to: string; compare_from: string | null; compare_to: string | null };
}

export default function ProfitAndLoss({ report, filters }: Props) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
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
                compare_from: compare ? compareFrom : null,
                compare_to: compare ? compareTo : null,
            },
            { preserveState: true },
        );
    }

    const showSecondary = Boolean(report.compare_from);
    const secondaryLabel = showSecondary ? `${report.compare_from} – ${report.compare_to}` : undefined;

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
                <form onSubmit={applyFilter} className="af-filter-bar mb-4">
                    <div style={{ maxWidth: '160px' }}>
                        <Input type="date" label="From" value={from} onChange={(e) => setFrom(e.target.value)} />
                    </div>
                    <div style={{ maxWidth: '160px' }}>
                        <Input type="date" label="To" value={to} onChange={(e) => setTo(e.target.value)} />
                    </div>
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
                                <Input type="date" label="Compare from" value={compareFrom} onChange={(e) => setCompareFrom(e.target.value)} />
                            </div>
                            <div style={{ maxWidth: '160px' }}>
                                <Input type="date" label="Compare to" value={compareTo} onChange={(e) => setCompareTo(e.target.value)} />
                            </div>
                        </>
                    )}
                    <Button type="submit" variant="outline">
                        Apply
                    </Button>
                </form>

                <ProfitLossSection
                    title="Revenue"
                    rows={report.revenue.map((row) => ({ key: row.account_id, label: row.name, amount: row.current, secondaryAmount: row.prior }))}
                    totalLabel="Total Revenue"
                    total={report.total_revenue.current}
                    secondaryTotal={report.total_revenue.prior}
                    secondaryColumnLabel={secondaryLabel}
                    emptyLabel="No revenue posted in this period."
                />

                <ProfitLossSection
                    title="Expenses"
                    rows={report.expenses.map((row) => ({ key: row.account_id, label: row.name, amount: row.current, secondaryAmount: row.prior }))}
                    totalLabel="Total Expenses"
                    total={report.total_expenses.current}
                    secondaryTotal={report.total_expenses.prior}
                    secondaryColumnLabel={secondaryLabel}
                    emptyLabel="No expenses posted in this period."
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
            </Card>
        </>
    );
}
