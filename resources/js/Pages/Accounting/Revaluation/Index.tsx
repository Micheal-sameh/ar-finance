import { Head, router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Input } from '@/Components/ui/Input';
import { Table } from '@/Components/ui/Table';
import { AppLayout } from '@/Layouts/AppLayout';

interface RevaluationRow {
    invoice_id: number;
    invoice_number: string;
    client_name: string;
    currency: string;
    foreign_amount: number;
    old_rate: number;
    new_rate: number;
    old_base_value: number;
    new_base_value: number;
    unrealized_gain_loss: number;
}

interface Preview {
    base_currency: string;
    as_of: string;
    rows: RevaluationRow[];
    total_unrealized_gain_loss: number;
}

interface Props {
    preview: Preview;
    filters: { date: string };
    canManage: boolean;
}

function formatRate(value: number): string {
    return value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 6 });
}

export default function RevaluationIndex({ preview, filters, canManage }: Props) {
    const [date, setDate] = useState(filters.date);
    const [fxGainLossAccountId, setFxGainLossAccountId] = useState<number | null>(null);
    const [running, setRunning] = useState(false);

    function applyFilter(e: FormEvent) {
        e.preventDefault();
        router.get(route('revaluation.index'), { date }, { preserveState: true });
    }

    function runRevaluation() {
        if (!fxGainLossAccountId) {
            return;
        }

        if (!confirm(`Post unrealized FX gain/loss for ${preview.rows.length} invoice(s) as of ${date}?`)) {
            return;
        }

        setRunning(true);
        router.post(
            route('revaluation.revalue'),
            { date, fx_gain_loss_account_id: fxGainLossAccountId },
            { preserveScroll: true, onFinish: () => setRunning(false) },
        );
    }

    return (
        <AppLayout>
            <Head title="Currency Revaluation" />

            <PageHeader
                title="Currency Revaluation"
                subtitle="Re-measures outstanding foreign-currency receivables against today's exchange rate and posts the unrealized gain or loss to the ledger. Bank accounts aren't included — those are kept correct through reconciliation, not revaluation."
            />

            <Card>
                <div className="d-flex align-items-end flex-wrap gap-2 mb-4">
                    <form onSubmit={applyFilter} className="d-flex align-items-end gap-2">
                        <div style={{ maxWidth: '200px' }}>
                            <Input type="date" label="As of" value={date} onChange={(e) => setDate(e.target.value)} />
                        </div>
                        <Button type="submit" variant="outline">
                            Apply
                        </Button>
                    </form>

                    {canManage && preview.rows.length > 0 && (
                        <>
                            <div style={{ maxWidth: '280px', flex: 1, minWidth: '220px' }}>
                                <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                    FX gain/loss account
                                </label>
                                <AccountPicker
                                    value={fxGainLossAccountId}
                                    onChange={setFxGainLossAccountId}
                                    placeholder="e.g. FX Gain/Loss"
                                />
                            </div>
                            <Button onClick={runRevaluation} loading={running} disabled={!fxGainLossAccountId}>
                                <RefreshCw size={14} className="me-1" />
                                Run Revaluation
                            </Button>
                        </>
                    )}
                </div>

                {preview.rows.length === 0 ? (
                    <EmptyState
                        icon={<RefreshCw size={20} />}
                        title="Nothing to revalue"
                        description="Every outstanding foreign-currency invoice is already booked at the current rate, or there are none outstanding."
                    />
                ) : (
                    <>
                        <Table>
                            <Table.Head>
                                <Table.HeadCell className="ps-3">Invoice</Table.HeadCell>
                                <Table.HeadCell>Client</Table.HeadCell>
                                <Table.HeadCell>Currency</Table.HeadCell>
                                <Table.HeadCell className="text-end">Old rate</Table.HeadCell>
                                <Table.HeadCell className="text-end">New rate</Table.HeadCell>
                                <Table.HeadCell className="text-end">Old value</Table.HeadCell>
                                <Table.HeadCell className="text-end">New value</Table.HeadCell>
                                <Table.HeadCell className="text-end pe-3">Unrealized gain/loss</Table.HeadCell>
                            </Table.Head>
                            <tbody>
                                {preview.rows.map((row) => (
                                    <Table.Row key={row.invoice_id}>
                                        <Table.Cell className="ps-3" style={{ fontWeight: 600 }}>
                                            {row.invoice_number}
                                        </Table.Cell>
                                        <Table.Cell>{row.client_name}</Table.Cell>
                                        <Table.Cell>
                                            <Badge variant="neutral">{row.currency}</Badge>
                                        </Table.Cell>
                                        <Table.Cell className="text-end">{formatRate(row.old_rate)}</Table.Cell>
                                        <Table.Cell className="text-end">{formatRate(row.new_rate)}</Table.Cell>
                                        <Table.Cell className="text-end">
                                            <MoneyDisplay amount={row.old_base_value} currency={preview.base_currency} />
                                        </Table.Cell>
                                        <Table.Cell className="text-end">
                                            <MoneyDisplay amount={row.new_base_value} currency={preview.base_currency} />
                                        </Table.Cell>
                                        <Table.Cell className="text-end pe-3">
                                            <span style={{ color: row.unrealized_gain_loss >= 0 ? 'var(--af-success)' : 'var(--af-danger)' }}>
                                                {row.unrealized_gain_loss >= 0 ? '+' : ''}
                                                <MoneyDisplay amount={row.unrealized_gain_loss} currency={preview.base_currency} />
                                            </span>
                                        </Table.Cell>
                                    </Table.Row>
                                ))}
                                <Table.Row style={{ fontWeight: 700, borderTop: '2px solid var(--af-navy)' }}>
                                    <Table.Cell className="ps-3" colSpan={7}>
                                        Net unrealized gain/loss
                                    </Table.Cell>
                                    <Table.Cell className="text-end pe-3">
                                        <span
                                            style={{
                                                color: preview.total_unrealized_gain_loss >= 0 ? 'var(--af-success)' : 'var(--af-danger)',
                                            }}
                                        >
                                            {preview.total_unrealized_gain_loss >= 0 ? '+' : ''}
                                            <MoneyDisplay amount={preview.total_unrealized_gain_loss} currency={preview.base_currency} />
                                        </span>
                                    </Table.Cell>
                                </Table.Row>
                            </tbody>
                        </Table>
                        {!canManage && (
                            <div className="mt-3" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                This is a preview only — you don't have permission to post the revaluation entry.
                            </div>
                        )}
                    </>
                )}
            </Card>
        </AppLayout>
    );
}
