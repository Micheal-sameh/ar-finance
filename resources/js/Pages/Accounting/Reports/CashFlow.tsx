import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { ProfitLossSection } from '@/Components/finance/ProfitLossSection';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { ExportButton } from '@/Components/ui/ExportButton';
import { Input } from '@/Components/ui/Input';
import type { CashFlowReport } from '@/types/finance';

interface Props {
    report: CashFlowReport;
    filters: { from: string; to: string };
}

export default function CashFlow({ report, filters }: Props) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    function applyFilter(e: FormEvent) {
        e.preventDefault();
        router.get(route('reports.cash-flow'), { from, to }, { preserveState: true });
    }

    const operatingRows = [
        { key: 'net-income', label: 'Net Income', amount: report.operating.net_income },
        ...report.operating.adjustments.map((row) => ({ key: `adj-${row.account_id}-${row.name}`, label: row.name, amount: row.amount })),
        ...report.operating.working_capital.map((row) => ({ key: row.account_id, label: row.name, amount: row.amount })),
    ];

    return (
        <>
            <Head title="Cash Flow Statement" />

            <PageHeader
                title="Cash Flow Statement"
                subtitle="Cash generated and used across operating, investing, and financing activities — indirect method, computed from posted journal lines."
                action={
                    <div className="d-flex align-items-center gap-2">
                        <Badge variant={report.is_reconciled ? 'success' : 'danger'}>
                            {report.is_reconciled ? 'Reconciled' : 'Out of balance'}
                        </Badge>
                        <ExportButton label="Download PDF" href={route('reports.cash-flow.pdf', filters)} target="_blank" />
                    </div>
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
                    <Button type="submit" variant="outline">
                        Apply
                    </Button>
                </form>

                <ProfitLossSection
                    title="Operating Activities"
                    rows={operatingRows}
                    totalLabel="Net Cash from Operating Activities"
                    total={report.operating.total}
                    emptyLabel="No operating activity in this period."
                />

                <ProfitLossSection
                    title="Investing Activities"
                    rows={report.investing.rows.map((row) => ({ key: row.account_id, label: row.name, amount: row.amount }))}
                    totalLabel="Net Cash from Investing Activities"
                    total={report.investing.total}
                    emptyLabel="No investing activity in this period."
                />

                <ProfitLossSection
                    title="Financing Activities"
                    rows={report.financing.rows.map((row) => ({ key: row.account_id, label: row.name, amount: row.amount }))}
                    totalLabel="Net Cash from Financing Activities"
                    total={report.financing.total}
                    emptyLabel="No financing activity in this period."
                />

                <div
                    className="d-flex align-items-center justify-content-between pt-3"
                    style={{ borderTop: '2px solid var(--af-navy)', fontSize: '16px', fontWeight: 700 }}
                >
                    <span>Net Change in Cash</span>
                    <span style={{ color: report.net_change_in_cash >= 0 ? 'var(--af-success)' : 'var(--af-danger)' }}>
                        <MoneyDisplay amount={report.net_change_in_cash} />
                    </span>
                </div>

                <div className="d-flex align-items-center justify-content-between pt-2" style={{ fontSize: '14px' }}>
                    <span>Cash at Beginning of Period</span>
                    <span>
                        <MoneyDisplay amount={report.beginning_cash} />
                    </span>
                </div>
                <div className="d-flex align-items-center justify-content-between pt-1" style={{ fontSize: '14px', fontWeight: 600 }}>
                    <span>Cash at End of Period</span>
                    <span>
                        <MoneyDisplay amount={report.ending_cash} />
                    </span>
                </div>
            </Card>
        </>
    );
}
