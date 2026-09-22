import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList, Plus } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { ExportButton } from '@/Components/ui/ExportButton';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import { Table } from '@/Components/ui/Table';
import { AppLayout } from '@/Layouts/AppLayout';
import type { Paginated, PurchaseOrder, PurchaseOrderStatus } from '@/types/finance';
import { formatDate } from '@/utils/finance';

interface Props {
    purchaseOrders: Paginated<PurchaseOrder>;
    filters: { status?: string; from?: string; to?: string; search?: string };
}

const PURCHASE_ORDER_STATUSES: { value: PurchaseOrderStatus; label: string }[] = [
    { value: 'draft', label: 'Draft' },
    { value: 'sent', label: 'Sent' },
    { value: 'closed', label: 'Closed' },
    { value: 'cancelled', label: 'Cancelled' },
];

function poTotal(po: PurchaseOrder): number {
    return po.lines.reduce((sum, line) => sum + parseFloat(line.quantity) * parseFloat(line.unit_price), 0);
}

function statusVariant(status: PurchaseOrderStatus) {
    return { draft: 'neutral', sent: 'info', closed: 'success', cancelled: 'danger' }[status] as
        | 'neutral'
        | 'info'
        | 'success'
        | 'danger';
}

export default function PurchaseOrdersIndex({ purchaseOrders, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function runSearch(e: FormEvent) {
        e.preventDefault();
        router.get(route('purchase-orders.index'), { ...filters, search }, { preserveState: true });
    }

    function runFilters(next: Partial<Props['filters']>) {
        router.get(route('purchase-orders.index'), { ...filters, search, ...next }, { preserveState: true });
    }

    function clearFilters() {
        setSearch('');
        router.get(route('purchase-orders.index'), {}, { preserveState: true });
    }

    return (
        <AppLayout>
            <Head title="Purchase Orders" />

            <PageHeader
                title="Purchase Orders"
                subtitle="Pre-commitments to vendors — convert one to a bill once the goods or work arrive."
                action={
                    <div className="d-flex gap-2">
                        <ExportButton href={route('purchase-orders.export', filters)} />
                        <Link href={route('purchase-orders.create')}>
                            <Button leadingIcon={<Plus size={16} />}>New Purchase Order</Button>
                        </Link>
                    </div>
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <form onSubmit={runSearch} className="d-flex gap-2 flex-wrap align-items-end">
                        <Input
                            placeholder="Search PO # or vendor…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            style={{ maxWidth: '240px' }}
                        />
                        <Select
                            value={filters.status ?? ''}
                            onChange={(e) => runFilters({ status: e.target.value || undefined })}
                            style={{ maxWidth: '150px' }}
                        >
                            <option value="">All statuses</option>
                            {PURCHASE_ORDER_STATUSES.map((s) => (
                                <option key={s.value} value={s.value}>
                                    {s.label}
                                </option>
                            ))}
                        </Select>
                        <Input
                            type="date"
                            label="From"
                            value={filters.from ?? ''}
                            onChange={(e) => runFilters({ from: e.target.value || undefined })}
                        />
                        <Input
                            type="date"
                            label="To"
                            value={filters.to ?? ''}
                            onChange={(e) => runFilters({ to: e.target.value || undefined })}
                        />
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                        {(filters.status || filters.from || filters.to || filters.search) && (
                            <Button type="button" variant="ghost" onClick={clearFilters}>
                                Clear
                            </Button>
                        )}
                    </form>
                </div>

                {purchaseOrders.data.length === 0 ? (
                    <EmptyState
                        icon={<ClipboardList size={20} />}
                        title="No purchase orders yet"
                        description="Create a purchase order to commit to a vendor before billing."
                        action={
                            <Link href={route('purchase-orders.create')}>
                                <Button>New Purchase Order</Button>
                            </Link>
                        }
                    />
                ) : (
                    <Table>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">PO Number</Table.HeadCell>
                            <Table.HeadCell>Vendor</Table.HeadCell>
                            <Table.HeadCell>Order date</Table.HeadCell>
                            <Table.HeadCell>Status</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Total</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {purchaseOrders.data.map((po) => (
                                <Table.Row key={po.id} style={{ cursor: 'pointer' }} onClick={() => router.get(route('purchase-orders.show', po.id))}>
                                    <Table.Cell className="ps-3">{po.po_number}</Table.Cell>
                                    <Table.Cell>{po.vendor?.name ?? '—'}</Table.Cell>
                                    <Table.Cell>{formatDate(po.order_date)}</Table.Cell>
                                    <Table.Cell>
                                        <Badge variant={statusVariant(po.status)}>{po.status}</Badge>
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
