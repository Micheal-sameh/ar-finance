import { Head, router } from '@inertiajs/react';
import { ArrowLeftRight, RefreshCw } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Input } from '@/Components/ui/Input';
import { Table } from '@/Components/ui/Table';
import { AppLayout } from '@/Layouts/AppLayout';

interface ExchangeRateRow {
    currency: string;
    rate: number;
    buy: number | null;
    sell: number | null;
    inverse_rate: number;
}

interface ExchangeRateReport {
    base_currency: string;
    requested_date: string;
    actual_date: string;
    is_stale: boolean;
    source: string | null;
    rows: ExchangeRateRow[];
    sync_error: string | null;
}

interface Props {
    report: ExchangeRateReport;
    canManage: boolean;
}

function formatRate(value: number): string {
    return value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
}

function sourceLabel(source: string | null): string {
    if (source === 'cbe.org.eg') {
        return 'Official Central Bank of Egypt rate';
    }

    if (source === 'fawazahmed0/exchange-api') {
        return 'Market rate (CBE unavailable for this date) — not an official CBE quote';
    }

    return '';
}

export default function ExchangeRatesIndex({ report, canManage }: Props) {
    const [date, setDate] = useState(report.requested_date);
    const [syncing, setSyncing] = useState(false);
    const hasBuySell = report.rows.some((row) => row.buy !== null);

    function applyFilter(e: FormEvent) {
        e.preventDefault();
        router.get(route('exchange-rates.index'), { date }, { preserveState: true });
    }

    function syncNow() {
        setSyncing(true);
        router.post(
            route('exchange-rates.sync'),
            { date },
            {
                preserveScroll: true,
                onFinish: () => setSyncing(false),
            },
        );
    }

    return (
        <AppLayout>
            <Head title="Exchange Rates" />

            <PageHeader
                title="Exchange Rates"
                subtitle={`All rates quoted as ${report.base_currency} per 1 unit of foreign currency — the same convention the Central Bank of Egypt uses for its own buy/sell quotes.`}
            />

            <Card>
                <div className="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-4">
                    <form onSubmit={applyFilter} className="d-flex align-items-end gap-2">
                        <div style={{ maxWidth: '200px' }}>
                            <Input type="date" label="Date" value={date} onChange={(e) => setDate(e.target.value)} />
                        </div>
                        <Button type="submit" variant="outline">
                            Apply
                        </Button>
                    </form>

                    {canManage && (
                        <Button variant="ghost" onClick={syncNow} loading={syncing}>
                            <RefreshCw size={14} className="me-1" />
                            Sync now
                        </Button>
                    )}
                </div>

                {report.rows.length > 0 && (
                    <div className="mb-3">
                        <Badge variant={report.source === 'cbe.org.eg' ? 'success' : 'warning'}>
                            {sourceLabel(report.source)}
                        </Badge>
                    </div>
                )}

                {report.is_stale && report.rows.length > 0 && (
                    <div className="mb-3">
                        <Badge variant="warning">
                            No rate published for {report.requested_date} — showing the last available rate, from{' '}
                            {report.actual_date}.
                        </Badge>
                    </div>
                )}

                {report.sync_error && report.rows.length === 0 && (
                    <div className="mb-3">
                        <Badge variant="danger">{report.sync_error}</Badge>
                    </div>
                )}

                {report.rows.length === 0 ? (
                    <EmptyState
                        icon={<ArrowLeftRight size={20} />}
                        title="No rates available"
                        description="The rate provider couldn't be reached and there's no cached rate for this date yet. Try again or pick another date."
                    />
                ) : (
                    <Table>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Currency</Table.HeadCell>
                            {hasBuySell ? (
                                <>
                                    <Table.HeadCell className="text-end">Buy</Table.HeadCell>
                                    <Table.HeadCell className="text-end">Sell</Table.HeadCell>
                                </>
                            ) : (
                                <Table.HeadCell className="text-end">{report.base_currency} per 1 unit</Table.HeadCell>
                            )}
                            <Table.HeadCell className="text-end pe-3">Units per 1 {report.base_currency}</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {report.rows.map((row) => (
                                <Table.Row key={row.currency}>
                                    <Table.Cell className="ps-3" style={{ fontWeight: 600 }}>
                                        {row.currency}
                                    </Table.Cell>
                                    {row.buy !== null && row.sell !== null ? (
                                        <>
                                            <Table.Cell className="text-end">{formatRate(row.buy)}</Table.Cell>
                                            <Table.Cell className="text-end">{formatRate(row.sell)}</Table.Cell>
                                        </>
                                    ) : (
                                        <Table.Cell className="text-end">{formatRate(row.rate)}</Table.Cell>
                                    )}
                                    <Table.Cell className="text-end pe-3">{formatRate(row.inverse_rate)}</Table.Cell>
                                </Table.Row>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>
        </AppLayout>
    );
}
