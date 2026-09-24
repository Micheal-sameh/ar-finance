import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Table } from '@/Components/ui/Table';
import { AppLayout } from '@/Layouts/AppLayout';
import type { Bill, BillStatus, PurchaseOrder, PurchaseOrderStatus, Vendor } from '@/types/finance';
import { formatDate, formatDateTime } from '@/utils/finance';

interface Summary {
    bill_count: number;
    total_billed: number;
    total_paid: number;
    outstanding: number;
}

interface Props {
    vendor: Vendor;
    bills: Bill[];
    purchaseOrders: PurchaseOrder[];
    summary: Summary;
}

function billStatusVariant(status: BillStatus) {
    return { draft: 'neutral', approved: 'warning', paid: 'success' }[status] as 'neutral' | 'warning' | 'success';
}

function poStatusVariant(status: PurchaseOrderStatus) {
    return { draft: 'neutral', sent: 'info', closed: 'success', cancelled: 'danger' }[status] as
        | 'neutral'
        | 'info'
        | 'success'
        | 'danger';
}

function billTotal(bill: Bill): number {
    return bill.lines.reduce(
        (sum, line) => sum + parseFloat(line.quantity) * parseFloat(line.unit_price) * (1 + parseFloat(line.tax_rate) / 100),
        0,
    );
}

function poTotal(po: PurchaseOrder): number {
    return po.lines.reduce((sum, line) => sum + parseFloat(line.quantity) * parseFloat(line.unit_price), 0);
}

function StatTile({ label, value }: { label: string; value: number }) {
    return (
        <div className="col-md-3">
            <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>{label}</div>
            <div style={{ fontSize: '20px', fontWeight: 600 }}>
                <MoneyDisplay amount={value} />
            </div>
        </div>
    );
}

export default function VendorsShow({ vendor, bills, purchaseOrders, summary }: Props) {
    return (
        <AppLayout>
            <Head title={vendor.name} />

            <PageHeader
                title={vendor.name}
                subtitle={vendor.email ?? undefined}
                action={
                    <div className="d-flex gap-2">
                        <Link href={route('purchase-orders.create')}>
                            <Button variant="outline" leadingIcon={<Plus size={16} />}>
                                New Purchase Order
                            </Button>
                        </Link>
                        <Link href={route('bills.create')}>
                            <Button leadingIcon={<Plus size={16} />}>New Bill</Button>
                        </Link>
                    </div>
                }
            />

            <Card className="mb-3">
                <div className="row g-3">
                    <div className="col-md-4">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Tax number</div>
                        <div>{vendor.tax_number ?? '—'}</div>
                    </div>
                    <div className="col-md-4">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Payment terms</div>
                        <div>{vendor.payment_terms ?? '—'}</div>
                    </div>
                    <div className="col-md-4">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Email</div>
                        <div>{vendor.email ?? '—'}</div>
                    </div>
                </div>
            </Card>

            <Card className="mb-3">
                <div className="row g-3">
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Bills</div>
                        <div style={{ fontSize: '20px', fontWeight: 600 }}>{summary.bill_count}</div>
                    </div>
                    <StatTile label="Total billed" value={summary.total_billed} />
                    <StatTile label="Total paid" value={summary.total_paid} />
                    <StatTile label="Outstanding" value={summary.outstanding} />
                </div>
            </Card>

            <Card padded={false} className="mb-3">
                <div className="px-3 pt-3 fw-medium">Bills</div>
                {bills.length === 0 ? (
                    <EmptyState title="No bills yet" description="This vendor has no bills on file." />
                ) : (
                    <Table>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Number</Table.HeadCell>
                            <Table.HeadCell>Bill date</Table.HeadCell>
                            <Table.HeadCell>Due date</Table.HeadCell>
                            <Table.HeadCell>Status</Table.HeadCell>
                            <Table.HeadCell className="text-end">Total</Table.HeadCell>
                            <Table.HeadCell className="pe-3">Paid at</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {bills.map((bill) => (
                                <Table.Row key={bill.id} style={{ cursor: 'pointer' }} onClick={() => router.get(route('bills.show', bill.id))}>
                                    <Table.Cell className="ps-3">{bill.bill_number}</Table.Cell>
                                    <Table.Cell>{formatDate(bill.bill_date)}</Table.Cell>
                                    <Table.Cell>{formatDate(bill.due_date)}</Table.Cell>
                                    <Table.Cell>
                                        <Badge variant={billStatusVariant(bill.status)}>{bill.status}</Badge>
                                    </Table.Cell>
                                    <Table.Cell className="text-end">
                                        <MoneyDisplay amount={billTotal(bill)} />
                                    </Table.Cell>
                                    <Table.Cell className="pe-3">{formatDateTime(bill.paid_at)}</Table.Cell>
                                </Table.Row>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>

            <Card padded={false}>
                <div className="px-3 pt-3 fw-medium">Purchase Orders</div>
                {purchaseOrders.length === 0 ? (
                    <EmptyState title="No purchase orders yet" description="This vendor has no purchase orders on file." />
                ) : (
                    <Table>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Number</Table.HeadCell>
                            <Table.HeadCell>Order date</Table.HeadCell>
                            <Table.HeadCell>Expected date</Table.HeadCell>
                            <Table.HeadCell>Status</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Total</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {purchaseOrders.map((po) => (
                                <Table.Row
                                    key={po.id}
                                    style={{ cursor: 'pointer' }}
                                    onClick={() => router.get(route('purchase-orders.show', po.id))}
                                >
                                    <Table.Cell className="ps-3">{po.po_number}</Table.Cell>
                                    <Table.Cell>{formatDate(po.order_date)}</Table.Cell>
                                    <Table.Cell>{formatDate(po.expected_date)}</Table.Cell>
                                    <Table.Cell>
                                        <Badge variant={poStatusVariant(po.status)}>{po.status}</Badge>
                                    </Table.Cell>
                                    <Table.Cell className="text-end pe-3">
                                        <MoneyDisplay amount={poTotal(po)} />
                                    </Table.Cell>
                                </Table.Row>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>
        </AppLayout>
    );
}
