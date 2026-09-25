import { Head, Link, router } from '@inertiajs/react';
import { FileText, Plus } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { ExportButton } from '@/Components/ui/ExportButton';
import { FilterPanel } from '@/Components/ui/FilterPanel';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import { Table } from '@/Components/ui/Table';
import type { Invoice, InvoiceStatus, Paginated } from '@/types/finance';
import { formatDate, invoiceStatusVariant } from '@/utils/finance';

interface Props {
    invoices: Paginated<Invoice>;
    filters: { status?: string; from?: string; to?: string; search?: string };
}

const INVOICE_STATUSES: { value: InvoiceStatus; label: string }[] = [
    { value: 'draft', label: 'Draft' },
    { value: 'sent', label: 'Sent' },
    { value: 'paid', label: 'Paid' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'void', label: 'Void' },
];

function invoiceTotal(invoice: Invoice): number {
    return invoice.lines.reduce((sum, line) => {
        const qty = parseFloat(line.quantity);
        const price = parseFloat(line.unit_price);
        const tax = parseFloat(line.tax_rate);

        return sum + qty * price * (1 + tax / 100);
    }, 0);
}

export default function InvoicesIndex({ invoices, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function runSearch(e: FormEvent) {
        e.preventDefault();
        router.get(route('invoices.index'), { ...filters, search }, { preserveState: true });
    }

    function runFilters(next: Partial<Props['filters']>) {
        router.get(route('invoices.index'), { ...filters, search, ...next }, { preserveState: true });
    }

    function clearFilters() {
        setSearch('');
        router.get(route('invoices.index'), {}, { preserveState: true });
    }

    return (
        <>
            <Head title="Invoices" />

            <PageHeader
                title="Invoices"
                subtitle="Revenue recognized against clients."
                action={
                    <div className="d-flex gap-2">
                        <ExportButton href={route('invoices.export', filters)} />
                        <Link href={route('invoices.create')}>
                            <Button leadingIcon={<Plus size={16} />}>New Invoice</Button>
                        </Link>
                    </div>
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <FilterPanel active={Boolean(filters.status || filters.from || filters.to || filters.search)}>
                    <form onSubmit={runSearch} className="af-filter-bar">
                        <Input
                            placeholder="Search invoice # or client…"
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
                            {INVOICE_STATUSES.map((s) => (
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
                    </FilterPanel>
                </div>

                {invoices.data.length === 0 ? (
                    <EmptyState
                        icon={<FileText size={20} />}
                        title="No invoices yet"
                        description="Create your first invoice to start billing clients."
                        action={
                            <Link href={route('invoices.create')}>
                                <Button>New Invoice</Button>
                            </Link>
                        }
                    />
                ) : (
                    <Table cards>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Number</Table.HeadCell>
                            <Table.HeadCell>Client</Table.HeadCell>
                            <Table.HeadCell>Issue date</Table.HeadCell>
                            <Table.HeadCell>Due date</Table.HeadCell>
                            <Table.HeadCell>Status</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Total</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {invoices.data.map((invoice) => (
                                <Table.Row key={invoice.id} style={{ cursor: 'pointer' }} onClick={() => router.get(route('invoices.show', invoice.id))}>
                                    <Table.Cell className="ps-3" label="Number">{invoice.invoice_number}</Table.Cell>
                                    <Table.Cell label="Client">{invoice.client?.name ?? '—'}</Table.Cell>
                                    <Table.Cell label="Issue date">{formatDate(invoice.issue_date)}</Table.Cell>
                                    <Table.Cell label="Due date">{formatDate(invoice.due_date)}</Table.Cell>
                                    <Table.Cell label="Status">
                                        <Badge variant={invoiceStatusVariant(invoice.status)}>{invoice.status}</Badge>
                                    </Table.Cell>
                                    <Table.Cell className="text-end pe-3" label="Total">
                                        <MoneyDisplay amount={invoiceTotal(invoice)} currency={invoice.currency} />
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
