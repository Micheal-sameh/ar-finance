import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { ProfitLossSection } from '@/Components/finance/ProfitLossSection';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Input } from '@/Components/ui/Input';
import { AppLayout } from '@/Layouts/AppLayout';
import type { BalanceSheetReport } from '@/types/finance';

interface Props {
    report: BalanceSheetReport;
    filters: { as_of: string };
}

export default function BalanceSheet({ report, filters }: Props) {
    const [asOf, setAsOf] = useState(filters.as_of);

    function applyFilter(e: FormEvent) {
        e.preventDefault();
        router.get(route('reports.balance-sheet'), { as_of: asOf }, { preserveState: true });
    }

    return (
        <AppLayout>
            <Head title="Balance Sheet" />

            <PageHeader
                title="Balance Sheet"
                subtitle="Assets, liabilities, and equity as of a date — computed from posted journal lines."
                action={
                    <Badge variant={report.is_balanced ? 'success' : 'danger'}>
                        {report.is_balanced ? 'Balanced' : 'Out of balance'}
                    </Badge>
                }
            />

            <Card>
                <form onSubmit={applyFilter} className="d-flex align-items-end gap-2 mb-4">
                    <div style={{ maxWidth: '180px' }}>
                        <Input type="date" label="As of" value={asOf} onChange={(e) => setAsOf(e.target.value)} />
                    </div>
                    <Button type="submit" variant="outline">
                        Apply
                    </Button>
                </form>

                <ProfitLossSection
                    title="Assets"
                    rows={report.assets.map((row) => ({ key: row.account_id, label: row.name, amount: row.balance }))}
                    totalLabel="Total Assets"
                    total={report.total_assets}
                    emptyLabel="No asset balances as of this date."
                />

                <ProfitLossSection
                    title="Liabilities"
                    rows={report.liabilities.map((row) => ({ key: row.account_id, label: row.name, amount: row.balance }))}
                    totalLabel="Total Liabilities"
                    total={report.total_liabilities}
                    emptyLabel="No liability balances as of this date."
                />

                <ProfitLossSection
                    title="Equity"
                    rows={report.equity.map((row) => ({ key: row.account_id, label: row.name, amount: row.balance }))}
                    totalLabel="Total Equity"
                    total={report.total_equity}
                    emptyLabel="No equity balances as of this date."
                />

                <div
                    className="d-flex align-items-center justify-content-between pt-3"
                    style={{ borderTop: '2px solid var(--af-navy)', fontSize: '16px', fontWeight: 700 }}
                >
                    <span>Liabilities + Equity</span>
                    <span>{(report.total_liabilities + report.total_equity).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} USD</span>
                </div>
            </Card>
        </AppLayout>
    );
}
