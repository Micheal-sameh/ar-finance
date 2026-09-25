import { Head, Link, router } from '@inertiajs/react';
import { Plus, ReceiptText } from 'lucide-react';
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
import type { Bill, BillStatus, Paginated } from '@/types/finance';
import { formatDate } from '@/utils/finance';

interface Props {
    bills: Paginated<Bill>;
    filters: { status?: string; from?: string; to?: string; search?: string };
}

const BILL_STATUSES: { value: BillStatus; label: string }[] = [
    { value: 'draft', label: 'Draft' },
    { value: 'approved', label: 'Approved' },
    { value: 'paid', label: 'Paid' },
];

function billTotal(bill: Bill): number {
    return bill.lines.reduce((sum, line) => sum + parseFloat(line.quantity) * parseFloat(line.unit_price), 0);
}

function statusVariant(status: BillStatus) {
    return { draft: 'neutral', approved: 'warning', paid: 'success' }[status] as 'neutral' | 'warning' | 'success';
}

export default function BillsIndex({ bills, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function runSearch(e: FormEvent) {
        e.preventDefault();
        router.get(route('bills.index'), { ...filters, search }, { preserveState: true });
    }

    function runFilters(next: Partial<Props['filters']>) {
        router.get(route('bills.index'), { ...filters, search, ...next }, { preserveState: true });
    }

    function clearFilters() {
        setSearch('');
        router.get(route('bills.index'), {}, { preserveState: true });
    }

    return (
        <>
            <Head title="Bills" />

            <PageHeader
                title="Bills"
                subtitle="Vendor bills — approving posts spend to the ledger."
                action={
                    <div className="d-flex gap-2">
                        <ExportButton href={route('bills.export', filters)} />
                        <Link href={route('bills.create')}>
                            <Button leadingIcon={<Plus size={16} />}>New Bill</Button>
                        </Link>
                    </div>
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <form onSubmit={runSearch} className="d-flex gap-2 flex-wrap align-items-end">
                        <Input
                            placeholder="Search bill # or vendor…"
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
                            {BILL_STATUSES.map((s) => (
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

                {bills.data.length === 0 ? (
                    <EmptyState
                        icon={<ReceiptText size={20} />}
                        title="No bills yet"
                        description="Create a bill directly, or convert a purchase order into one."
                        action={
                            <Link href={route('bills.create')}>
                                <Button>New Bill</Button>
                            </Link>
                        }
                    />
                ) : (
                    <Table cards>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Bill Number</Table.HeadCell>
                            <Table.HeadCell>Vendor</Table.HeadCell>
                            <Table.HeadCell>Due date</Table.HeadCell>
                            <Table.HeadCell>Status</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Total</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {bills.data.map((bill) => (
                                <Table.Row key={bill.id} style={{ cursor: 'pointer' }} onClick={() => router.get(route('bills.show', bill.id))}>
                                    <Table.Cell className="ps-3" label="Bill Number">{bill.bill_number}</Table.Cell>
                                    <Table.Cell label="Vendor">{bill.vendor?.name ?? '—'}</Table.Cell>
                                    <Table.Cell label="Due date">{formatDate(bill.due_date)}</Table.Cell>
                                    <Table.Cell label="Status">
                                        <Badge variant={statusVariant(bill.status)}>{bill.status}</Badge>
                                    </Table.Cell>
                                    <Table.Cell className="text-end pe-3" label="Total">
                                        <MoneyDisplay amount={billTotal(bill)} />
                                    </Table.Cell>
                                </Table.Row>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>
        </>
    );
}
