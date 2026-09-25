import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Table } from '@/Components/ui/Table';
import type { Client, Invoice } from '@/types/finance';
import { formatDate, formatDateTime, invoiceStatusVariant } from '@/utils/finance';

interface Summary {
    invoice_count: number;
    total_invoiced: number;
    total_paid: number;
    outstanding: number;
}

interface Props {
    client: Client;
    invoices: Invoice[];
    summary: Summary;
}

function invoiceTotal(invoice: Invoice): number {
    return invoice.lines.reduce(
        (sum, line) => sum + parseFloat(line.quantity) * parseFloat(line.unit_price) * (1 + parseFloat(line.tax_rate) / 100),
        0,
    );
}

function StatTile({ label, value, currency }: { label: string; value: number; currency: string }) {
    return (
        <div className="col-md-3">
            <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>{label}</div>
            <div style={{ fontSize: '20px', fontWeight: 600 }}>
                <MoneyDisplay amount={value} currency={currency} />
            </div>
        </div>
    );
}

export default function ClientsShow({ client, invoices, summary }: Props) {
    return (
        <>
            <Head title={client.name} />

            <PageHeader
                title={client.name}
                subtitle={client.email ?? undefined}
                action={
                    <Link href={route('invoices.create')}>
                        <Button leadingIcon={<Plus size={16} />}>New Invoice</Button>
                    </Link>
                }
            />

            <Card className="mb-3">
                <div className="row g-3">
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Phone</div>
                        <div>{client.phone ?? '—'}</div>
                    </div>
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Tax number</div>
                        <div>{client.tax_number ?? '—'}</div>
                    </div>
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Currency</div>
                        <div>{client.currency}</div>
                    </div>
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Address</div>
                        <div>{client.address ?? '—'}</div>
                    </div>
                </div>
            </Card>

            <Card className="mb-3">
                <div className="row g-3">
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Invoices</div>
                        <div style={{ fontSize: '20px', fontWeight: 600 }}>{summary.invoice_count}</div>
                    </div>
                    <StatTile label="Total invoiced" value={summary.total_invoiced} currency={client.currency} />
                    <StatTile label="Total paid" value={summary.total_paid} currency={client.currency} />
                    <StatTile label="Outstanding" value={summary.outstanding} currency={client.currency} />
                </div>
            </Card>

            <Card padded={false}>
                {invoices.length === 0 ? (
                    <EmptyState title="No invoices yet" description="This client has no invoices on file." />
                ) : (
                    <Table>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Number</Table.HeadCell>
                            <Table.HeadCell>Issue date</Table.HeadCell>
                            <Table.HeadCell>Due date</Table.HeadCell>
                            <Table.HeadCell>Status</Table.HeadCell>
                            <Table.HeadCell className="text-end">Total</Table.HeadCell>
                            <Table.HeadCell className="pe-3">Paid at</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {invoices.map((invoice) => (
                                <Table.Row
                                    key={invoice.id}
                                    style={{ cursor: 'pointer' }}
                                    onClick={() => router.get(route('invoices.show', invoice.id))}
                                >
                                    <Table.Cell className="ps-3">{invoice.invoice_number}</Table.Cell>
                                    <Table.Cell>{formatDate(invoice.issue_date)}</Table.Cell>
                                    <Table.Cell>{formatDate(invoice.due_date)}</Table.Cell>
                                    <Table.Cell>
                                        <Badge variant={invoiceStatusVariant(invoice.status)}>{invoice.status}</Badge>
                                    </Table.Cell>
                                    <Table.Cell className="text-end">
                                        <MoneyDisplay amount={invoiceTotal(invoice)} currency={invoice.currency} />
                                    </Table.Cell>
                                    <Table.Cell className="pe-3">{formatDateTime(invoice.paid_at)}</Table.Cell>
                                </Table.Row>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>
        </>
    );
}
