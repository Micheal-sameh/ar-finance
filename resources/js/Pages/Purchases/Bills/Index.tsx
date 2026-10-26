import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus, ReceiptText, Wallet } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { ExportButton } from '@/Components/ui/ExportButton';
import { FilterPanel } from '@/Components/ui/FilterPanel';
import { Input } from '@/Components/ui/Input';
import { InfoTip } from '@/Components/ui/InfoTip';
import { Modal } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { Table } from '@/Components/ui/Table';
import type { Bill, BillStatus, Paginated } from '@/types/finance';
import { billBalanceDue, billIsPayable, billStatusLabel, billStatusVariant, billTotal, formatDate } from '@/utils/finance';

interface Props {
    bills: Paginated<Bill>;
    filters: { status?: string; from?: string; to?: string; search?: string };
    viewingAllTenants: boolean;
}

const BILL_STATUSES: { value: BillStatus; label: string }[] = [
    { value: 'draft', label: 'Draft' },
    { value: 'approved', label: 'Approved' },
    { value: 'partially_paid', label: 'Partially paid' },
    { value: 'paid', label: 'Paid' },
];

export default function BillsIndex({ bills, filters, viewingAllTenants }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [selected, setSelected] = useState<Set<number>>(new Set());
    const [payOpen, setPayOpen] = useState(false);

    const payForm = useForm({
        payment_account_id: null as number | null,
        paid_on: new Date().toISOString().slice(0, 10),
        reference: '',
    });

    const payableBills = bills.data.filter(billIsPayable);
    const selectedBills = payableBills.filter((bill) => selected.has(bill.id));
    const selectedTotal = selectedBills.reduce((sum, bill) => sum + billBalanceDue(bill), 0);
    const allSelected = payableBills.length > 0 && selectedBills.length === payableBills.length;

    function toggle(id: number) {
        setSelected((current) => {
            const next = new Set(current);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    }

    function toggleAll() {
        setSelected(allSelected ? new Set() : new Set(payableBills.map((bill) => bill.id)));
    }

    function paySelected() {
        payForm.transform((data) => ({ ...data, bill_ids: selectedBills.map((bill) => bill.id) }));
        payForm.post(route('bills.pay-batch'), {
            onSuccess: () => {
                setPayOpen(false);
                setSelected(new Set());
                payForm.reset('reference');
            },
        });
    }

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
                subtitle={
                    viewingAllTenants
                        ? 'Every service’s bills, combined.'
                        : 'Vendor bills — approving posts spend to the ledger.'
                }
                action={
                    (
                        <div className="d-flex gap-2">
                            {selectedBills.length > 0 && (
                                <Button variant="outline" leadingIcon={<Wallet size={16} />} onClick={() => setPayOpen(true)}>
                                    Pay selected ({selectedBills.length})
                                </Button>
                            )}
                            {!viewingAllTenants && <ExportButton href={route('bills.export', filters)} />}
                            <Link href={route('bills.create')}>
                                <Button leadingIcon={<Plus size={16} />}>New Bill</Button>
                            </Link>
                        </div>
                    )
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <FilterPanel active={Boolean(filters.status || filters.from || filters.to || filters.search)}>
                    <form onSubmit={runSearch} className="af-filter-bar">
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
                            help="The first day to include. Only entries dated on or after this day are counted."
                            value={filters.from ?? ''}
                            onChange={(e) => runFilters({ from: e.target.value || undefined })}
                        />
                        <Input
                            type="date"
                            label="To"
                            help="The last day to include. Only entries dated on or before this day are counted."
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

                {bills.data.length === 0 ? (
                    <EmptyState
                        icon={<ReceiptText size={20} />}
                        title="No bills yet"
                        description="Create a bill directly, or convert a purchase order into one."
                        action={
                            (
                                <Link href={route('bills.create')}>
                                    <Button>New Bill</Button>
                                </Link>
                            )
                        }
                    />
                ) : (
                    <Table cards>
                        <Table.Head>
                            {!viewingAllTenants && (
                                <Table.HeadCell className="ps-3" style={{ width: '40px' }}>
                                    <input
                                        type="checkbox"
                                        aria-label="Select all payable bills"
                                        checked={allSelected}
                                        disabled={payableBills.length === 0}
                                        onChange={toggleAll}
                                    />
                                </Table.HeadCell>
                            )}
                            <Table.HeadCell className={viewingAllTenants ? 'ps-3' : undefined}>Bill Number</Table.HeadCell>
                            <Table.HeadCell>Vendor</Table.HeadCell>
                            <Table.HeadCell>Due date</Table.HeadCell>
                            <Table.HeadCell>Status</Table.HeadCell>
                            {viewingAllTenants && <Table.HeadCell>Service</Table.HeadCell>}
                            <Table.HeadCell className="text-end">Total</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Balance due</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {bills.data.map((bill) => (
                                <Table.Row
                                    key={bill.id}
                                    style={{ cursor: 'pointer' }}
                                    onClick={() => router.get(route('bills.show', { bill: bill.id, ...(viewingAllTenants ? { for_tenant: bill.tenant_id } : {}) }))}
                                >
                                    {!viewingAllTenants && (
                                        <Table.Cell className="ps-3" onClick={(e) => e.stopPropagation()}>
                                            {billIsPayable(bill) && (
                                                <input
                                                    type="checkbox"
                                                    aria-label={`Select bill ${bill.bill_number}`}
                                                    checked={selected.has(bill.id)}
                                                    onChange={() => toggle(bill.id)}
                                                />
                                            )}
                                        </Table.Cell>
                                    )}
                                    <Table.Cell className={viewingAllTenants ? 'ps-3' : undefined} label="Bill Number">{bill.bill_number}</Table.Cell>
                                    <Table.Cell label="Vendor">{bill.vendor?.name ?? '—'}</Table.Cell>
                                    <Table.Cell label="Due date">{formatDate(bill.due_date)}</Table.Cell>
                                    <Table.Cell label="Status">
                                        <Badge variant={billStatusVariant(bill.status)}>{billStatusLabel(bill.status)}</Badge>
                                    </Table.Cell>
                                    {viewingAllTenants && <Table.Cell label="Service">{bill.tenant_name}</Table.Cell>}
                                    <Table.Cell className="text-end" label="Total">
                                        <MoneyDisplay amount={billTotal(bill)} />
                                    </Table.Cell>
                                    <Table.Cell className="text-end pe-3" label="Balance due">
                                        {billIsPayable(bill) ? <MoneyDisplay amount={billBalanceDue(bill)} /> : '—'}
                                    </Table.Cell>
                                </Table.Row>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>
            <Modal
                open={payOpen}
                onClose={() => setPayOpen(false)}
                title={`Pay ${selectedBills.length} bill(s)`}
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setPayOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={paySelected} loading={payForm.processing}>
                            Pay {selectedTotal.toFixed(2)}
                        </Button>
                    </>
                }
            >
                <div className="d-flex flex-column gap-3">
                    <div style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                        Each selected bill is paid in full (its remaining balance) from the same account.
                    </div>
                    <div>
                        <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                            Pay from <InfoTip>The bank or cash account every selected bill is paid from.</InfoTip>
                        </label>
                        <AccountPicker
                            value={payForm.data.payment_account_id}
                            onChange={(id) => payForm.setData('payment_account_id', id)}
                            error={payForm.errors.payment_account_id}
                            placeholder="e.g. Bank account"
                        />
                    </div>
                    <Input
                        label="Payment date"
                        help="The day the money left the account. Each payment is recorded in the books on this date."
                        type="date"
                        value={payForm.data.paid_on}
                        onChange={(e) => payForm.setData('paid_on', e.target.value)}
                        error={payForm.errors.paid_on}
                    />
                    <Input
                        label="Reference"
                        help="Optional. A transfer or batch number so you can find these payments on the bank statement later."
                        maxLength={100}
                        value={payForm.data.reference}
                        onChange={(e) => payForm.setData('reference', e.target.value)}
                        error={payForm.errors.reference}
                    />
                    {(payForm.errors as Record<string, string>).bill_ids && (
                        <div style={{ color: 'var(--af-danger)', fontSize: '12px' }}>{(payForm.errors as Record<string, string>).bill_ids}</div>
                    )}
                    <div>
                        {selectedBills.map((bill) => (
                            <div key={bill.id} className="d-flex justify-content-between py-1" style={{ fontSize: '13px' }}>
                                <span>
                                    {bill.bill_number} · {bill.vendor?.name ?? '—'}
                                </span>
                                <MoneyDisplay amount={billBalanceDue(bill)} />
                            </div>
                        ))}
                    </div>
                </div>
            </Modal>
        </>
    );
}
