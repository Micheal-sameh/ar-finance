import { Head, Link, router } from '@inertiajs/react';
import { HandCoins, Plus } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { FilterPanel } from '@/Components/ui/FilterPanel';
import { Input } from '@/Components/ui/Input';
import { Table } from '@/Components/ui/Table';
import type { Paginated, ReceivedPayment } from '@/types/finance';
import { formatDate } from '@/utils/finance';

interface Props {
    receivedPayments: Paginated<ReceivedPayment>;
    filters: { from?: string; to?: string; search?: string };
    viewingAllTenants: boolean;
}

export default function ReceivedPaymentsIndex({ receivedPayments, filters, viewingAllTenants }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const hasFilters = Boolean(filters.from || filters.to || filters.search);

    function runSearch(e: FormEvent) {
        e.preventDefault();
        router.get(route('received-payments.index'), { ...filters, search }, { preserveState: true });
    }

    function runFilters(next: Partial<Props['filters']>) {
        router.get(route('received-payments.index'), { ...filters, search, ...next }, { preserveState: true });
    }

    function clearFilters() {
        setSearch('');
        router.get(route('received-payments.index'), {}, { preserveState: true });
    }

    return (
        <>
            <Head title="Received Payments" />

            <PageHeader
                title="Received Payments"
                subtitle={
                    viewingAllTenants
                        ? 'Every service’s received payments, combined.'
                        : 'Money received that isn’t tied to an invoice.'
                }
                action={
                    (
                        <Link href={route('received-payments.create')}>
                            <Button leadingIcon={<Plus size={16} />}>Receive Payment</Button>
                        </Link>
                    )
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <FilterPanel active={hasFilters}>
                        <form onSubmit={runSearch} className="d-flex flex-column gap-2">
                            <Input
                                placeholder="Search description or reference…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                style={{ maxWidth: '320px' }}
                            />
                            <div className="d-grid gap-2" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))' }}>
                                <Input
                                    type="date"
                                    label="From"
                                    help="The first day to include. Only payments received on or after this day are counted."
                                    value={filters.from ?? ''}
                                    onChange={(e) => runFilters({ from: e.target.value || undefined })}
                                />
                                <Input
                                    type="date"
                                    label="To"
                                    help="The last day to include. Only payments received on or before this day are counted."
                                    value={filters.to ?? ''}
                                    onChange={(e) => runFilters({ to: e.target.value || undefined })}
                                />
                            </div>
                            <div className="d-flex gap-2">
                                <Button type="submit" variant="outline">
                                    Search
                                </Button>
                                {hasFilters && (
                                    <Button type="button" variant="ghost" onClick={clearFilters}>
                                        Clear
                                    </Button>
                                )}
                            </div>
                        </form>
                    </FilterPanel>
                </div>

                {receivedPayments.data.length === 0 ? (
                    <EmptyState
                        icon={<HandCoins size={20} />}
                        title="No received payments yet"
                        description="Record money you received without an invoice, such as an advance or deposit."
                        action={
                            (
                                <Link href={route('received-payments.create')}>
                                    <Button>Receive Payment</Button>
                                </Link>
                            )
                        }
                    />
                ) : (
                    <Table cards>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Date</Table.HeadCell>
                            <Table.HeadCell>Description</Table.HeadCell>
                            <Table.HeadCell>Client</Table.HeadCell>
                            <Table.HeadCell>Received into</Table.HeadCell>
                            <Table.HeadCell>Received for</Table.HeadCell>
                            {viewingAllTenants && <Table.HeadCell>Service</Table.HeadCell>}
                            <Table.HeadCell className="text-end pe-3">Amount</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {receivedPayments.data.map((payment) => (
                                <Table.Row key={payment.id}>
                                    <Table.Cell className="ps-3" label="Date">{formatDate(payment.received_on)}</Table.Cell>
                                    <Table.Cell label="Description">{payment.description}</Table.Cell>
                                    <Table.Cell label="Client">{payment.client?.name ?? '—'}</Table.Cell>
                                    <Table.Cell label="Received into">{payment.deposit_account?.name ?? '—'}</Table.Cell>
                                    <Table.Cell label="Received for">{payment.credit_account?.name ?? '—'}</Table.Cell>
                                    {viewingAllTenants && <Table.Cell label="Service">{payment.tenant_name}</Table.Cell>}
                                    <Table.Cell className="text-end pe-3" label="Amount">
                                        <MoneyDisplay amount={payment.amount} />
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
