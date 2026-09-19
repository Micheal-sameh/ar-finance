import { Head } from '@inertiajs/react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Card } from '@/Components/ui/Card';
import { Table } from '@/Components/ui/Table';
import { AppLayout } from '@/Layouts/AppLayout';
import type { JournalEntry } from '@/types/finance';
import { formatDate, formatDateTime } from '@/utils/finance';

interface Props {
    entry: JournalEntry;
}

export default function JournalsShow({ entry }: Props) {
    const totalDebit = entry.lines.reduce((sum, line) => sum + parseFloat(line.debit), 0);
    const totalCredit = entry.lines.reduce((sum, line) => sum + parseFloat(line.credit), 0);

    return (
        <AppLayout>
            <Head title={`Journal Entry #${entry.id}`} />

            <PageHeader
                title={`Journal Entry #${entry.id}`}
                subtitle={entry.description}
                action={<Badge variant={entry.posted_at ? 'success' : 'neutral'}>{entry.posted_at ? 'Posted' : 'Draft'}</Badge>}
            />

            <Card padded={false}>
                <div className="row g-3 p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Date</div>
                        <div>{formatDate(entry.date)}</div>
                    </div>
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Reference</div>
                        <div>{entry.reference ?? '—'}</div>
                    </div>
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Source</div>
                        <div style={{ textTransform: 'capitalize' }}>{entry.source_type}</div>
                    </div>
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Posted at</div>
                        <div>{formatDateTime(entry.posted_at)}</div>
                    </div>
                </div>

                <Table>
                    <Table.Head>
                        <Table.HeadCell className="ps-3">Account</Table.HeadCell>
                        <Table.HeadCell>Description</Table.HeadCell>
                        <Table.HeadCell className="text-end">Debit</Table.HeadCell>
                        <Table.HeadCell className="text-end pe-3">Credit</Table.HeadCell>
                    </Table.Head>
                    <tbody>
                        {entry.lines.map((line) => (
                            <Table.Row key={line.id}>
                                <Table.Cell className="ps-3">
                                    {line.account ? `${line.account.code} · ${line.account.name}` : '—'}
                                </Table.Cell>
                                <Table.Cell>{line.description ?? '—'}</Table.Cell>
                                <Table.Cell className="text-end">
                                    {parseFloat(line.debit) > 0 && <MoneyDisplay amount={line.debit} />}
                                </Table.Cell>
                                <Table.Cell className="text-end pe-3">
                                    {parseFloat(line.credit) > 0 && <MoneyDisplay amount={line.credit} />}
                                </Table.Cell>
                            </Table.Row>
                        ))}
                        <Table.Row style={{ fontWeight: 600 }}>
                            <Table.Cell className="ps-3" colSpan={2}>
                                Total
                            </Table.Cell>
                            <Table.Cell className="text-end">
                                <MoneyDisplay amount={totalDebit} />
                            </Table.Cell>
                            <Table.Cell className="text-end pe-3">
                                <MoneyDisplay amount={totalCredit} />
                            </Table.Cell>
                        </Table.Row>
                    </tbody>
                </Table>
            </Card>
        </AppLayout>
    );
}
