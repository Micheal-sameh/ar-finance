import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Icon } from '@/Components/layout/Icon';
import { PageHeader } from '@/Components/layout/PageHeader';
import { ExportButton } from '@/Components/ui/ExportButton';
import type { Invoice, InvoiceStatus, Paginated } from '@/types/finance';

interface Props {
    invoices: Paginated<Invoice>;
    filters: { status?: string; from?: string; to?: string; search?: string };
    statusCounts: Partial<Record<InvoiceStatus, number>>;
    viewingAllTenants: boolean;
}

const TABS: { value: InvoiceStatus | ''; label: string }[] = [
    { value: '', label: 'All' },
    { value: 'draft', label: 'Draft' },
    { value: 'sent', label: 'Sent' },
    { value: 'paid', label: 'Paid' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'void', label: 'Void' },
];

const statusBadge: Record<InvoiceStatus, string> = {
    draft: 'neutral',
    sent: 'info',
    paid: 'success',
    overdue: 'error',
    void: 'neutral',
};

function invoiceTotal(invoice: Invoice): number {
    return invoice.lines.reduce((sum, line) => {
        const qty = parseFloat(line.quantity);
        const price = parseFloat(line.unit_price);
        const tax = parseFloat(line.tax_rate);

        return sum + qty * price * (1 + tax / 100);
    }, 0);
}

function formatMoney(value: number, currency: string): string {
    return `${currency} ${value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatDay(value: string): string {
    return new Date(value).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function InvoicesIndex({ invoices, filters, statusCounts, viewingAllTenants }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const total = Object.values(statusCounts).reduce((sum, count) => sum + Number(count), 0);
    const from = invoices.data.length === 0 ? 0 : (invoices.current_page - 1) * invoices.per_page + 1;
    const to = from === 0 ? 0 : from + invoices.data.length - 1;

    function runFilters(next: Partial<Props['filters']>) {
        router.get(route('invoices.index'), { ...filters, search, ...next }, { preserveState: true });
    }

    function runSearch(event: FormEvent) {
        event.preventDefault();
        runFilters({});
    }

    return (
        <>
            <Head title="Invoices" />

            <PageHeader
                title="Invoices"
                subtitle={
                    viewingAllTenants
                        ? 'Every tenant’s invoices, combined. Switch into a tenant to manage one.'
                        : 'Create, send, and track customer invoices.'
                }
                action={
                    !viewingAllTenants && (
                        <Link href={route('invoices.create')} className="button primary">
                            <Icon name="plus" size={17} />
                            New invoice
                        </Link>
                    )
                }
            />

            <div className="tabs">
                {TABS.map((tab) => (
                    <Link
                        key={tab.value}
                        href={route('invoices.index', { ...filters, status: tab.value || undefined })}
                        preserveState
                        className={(filters.status ?? '') === tab.value ? 'active' : ''}
                    >
                        {tab.label}
                        <span>{tab.value === '' ? total : (statusCounts[tab.value] ?? 0)}</span>
                    </Link>
                ))}
            </div>

            <article className="card list-card">
                <form className="list-toolbar" onSubmit={runSearch}>
                    <div className="table-search">
                        <Icon name="search" />
                        <input
                            placeholder="Search by invoice or customer..."
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>
                    <input
                        type="date"
                        aria-label="From"
                        value={filters.from ?? ''}
                        onChange={(event) => runFilters({ from: event.target.value || undefined })}
                    />
                    <input
                        type="date"
                        aria-label="To"
                        value={filters.to ?? ''}
                        onChange={(event) => runFilters({ to: event.target.value || undefined })}
                    />
                    <span className="toolbar-spacer" />
                    {!viewingAllTenants && <ExportButton href={route('invoices.export', filters)} />}
                </form>

                <div className="table-wrap invoice-table">
                    <table>
                        <thead>
                            <tr>
                                <th>INVOICE</th>
                                <th>CUSTOMER</th>
                                {viewingAllTenants && <th>TENANT</th>}
                                <th>ISSUED</th>
                                <th>DUE DATE</th>
                                <th className="align-right">AMOUNT</th>
                                <th>STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            {invoices.data.length === 0 && (
                                <tr>
                                    <td colSpan={7}>No invoices found. Create your first invoice to start billing clients.</td>
                                </tr>
                            )}
                            {invoices.data.map((invoice) => (
                                <tr
                                    key={invoice.id}
                                    style={viewingAllTenants ? undefined : { cursor: 'pointer' }}
                                    onClick={viewingAllTenants ? undefined : () => router.get(route('invoices.show', invoice.id))}
                                >
                                    <td>
                                        <b className="link">{invoice.invoice_number}</b>
                                    </td>
                                    <td>
                                        <b>{invoice.client?.name ?? '—'}</b>
                                    </td>
                                    {viewingAllTenants && <td>{invoice.tenant_name}</td>}
                                    <td>{formatDay(invoice.issue_date)}</td>
                                    <td>{formatDay(invoice.due_date)}</td>
                                    <td className="align-right">
                                        <span className="money">{formatMoney(invoiceTotal(invoice), invoice.currency)}</span>
                                    </td>
                                    <td>
                                        <span className={`badge ${statusBadge[invoice.status]}`}>
                                            <i />
                                            {invoice.status.charAt(0).toUpperCase() + invoice.status.slice(1)}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="pagination">
                    <span>
                        Showing {from}–{to} of {invoices.total} invoices
                    </span>
                    <div>
                        {invoices.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url ?? '#'}
                                preserveState
                                className={`${link.active ? 'active' : ''} ${link.url ? '' : 'disabled'}`}
                            >
                                <span dangerouslySetInnerHTML={{ __html: link.label }} />
                            </Link>
                        ))}
                    </div>
                </div>
            </article>
        </>
    );
}
