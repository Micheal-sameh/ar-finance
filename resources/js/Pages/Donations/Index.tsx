import { Head, Link, router, useForm } from '@inertiajs/react';
import { Banknote, CreditCard, FileDown, HeartHandshake, Landmark, Plus, Printer, UserPlus, XCircle } from 'lucide-react';
import { FormEvent, type ReactNode, useState } from 'react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { useConfirm } from '@/Components/ui/ConfirmProvider';
import { EmptyState } from '@/Components/ui/EmptyState';
import { FilterPanel } from '@/Components/ui/FilterPanel';
import { InfoTip } from '@/Components/ui/InfoTip';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { SearchableSelect } from '@/Components/ui/SearchableSelect';
import { Select } from '@/Components/ui/Select';
import { Table } from '@/Components/ui/Table';
import type { Donation, DonationFilters, DonationOptions, DonationPaymentMethod, DonationsPage } from '@/types/donations';

interface Props {
    donations: DonationsPage;
    filters: DonationFilters;
    options: DonationOptions;
    canCreate: boolean;
}

const METHOD_ICONS: Record<DonationPaymentMethod, ReactNode> = {
    cash: <Banknote size={16} />,
    bank_transfer: <Landmark size={16} />,
    pos: <CreditCard size={16} />,
};

const today = () => new Date().toISOString().slice(0, 10);

export default function DonationsIndex({ donations, filters, options, canCreate }: Props) {
    const confirm = useConfirm();
    const [modalOpen, setModalOpen] = useState(false);
    const [donorModalOpen, setDonorModalOpen] = useState(false);
    const [formError, setFormError] = useState<string | null>(null);
    const [filterValues, setFilterValues] = useState({
        search: filters.search ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
        cost_center_id: filters.cost_center_id ? String(filters.cost_center_id) : '',
        currency: filters.currency ?? '',
        payment_method: filters.payment_method ?? '',
        status: filters.status ?? '',
        created_by: filters.created_by ? String(filters.created_by) : '',
    });

    const emptyForm = {
        donor_id: '' as number | '',
        cost_center_id: '' as number | '',
        amount: '',
        currency: options.baseCurrency,
        exchange_rate: '',
        donated_at: today(),
        payment_method: 'cash' as DonationPaymentMethod,
        payment_reference: '',
        notes: '',
    };

    const form = useForm(emptyForm);
    const donorForm = useForm({ name: '', phone: '', email: '', notes: '' });
    const isForeign = form.data.currency !== options.baseCurrency;
    const hasFilters = Object.values(filterValues).some((value) => value !== '');
    const from = donations.total === 0 ? 0 : (donations.current_page - 1) * donations.per_page + 1;
    const to = Math.min(donations.current_page * donations.per_page, donations.total);

    function openCreate() {
        form.setData(emptyForm);
        form.clearErrors();
        setFormError(null);
        setModalOpen(true);
    }

    /** Starts the receipt PDF download without leaving the page. */
    function downloadReceipt(url: string) {
        const link = document.createElement('a');
        link.href = url;
        link.download = '';
        document.body.appendChild(link);
        link.click();
        link.remove();
    }

    function submit(e: FormEvent, printAfter = false) {
        e.preventDefault();
        setFormError(null);

        // Without preserveState the page remounts on the redirect back and a
        // refused donation (e.g. a missing account mapping) would lose the form.
        form.transform((data) => ({
            ...data,
            donor_id: data.donor_id === '' ? null : data.donor_id,
            exchange_rate: isForeign && data.exchange_rate !== '' ? data.exchange_rate : null,
        }));

        form.post(route('donations.store'), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = page.props.flash as { error?: string; receipt_url?: string } | undefined;

                if (flash?.error) {
                    setFormError(flash.error);
                } else {
                    setModalOpen(false);
                    form.setData(emptyForm);

                    if (printAfter && flash?.receipt_url) downloadReceipt(flash.receipt_url);
                }
            },
        });
    }

    function submitDonor(e: FormEvent) {
        e.preventDefault();
        const knownIds = new Set(options.donors.map((donor) => donor.id));

        donorForm.post(route('donors.store'), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const added = (page.props.options as DonationOptions).donors.find((donor) => !knownIds.has(donor.id));

                if (added) form.setData('donor_id', added.id);
                setDonorModalOpen(false);
                donorForm.reset();
            },
        });
    }

    async function cancelDonation(donation: Donation) {
        const confirmed = await confirm(`Cancel donation #${donation.receipt_number}? Its journal entries will be reversed.`, {
            variant: 'danger',
            confirmLabel: 'Cancel donation',
        });

        if (confirmed) {
            router.post(route('donations.cancel', donation.id), {}, { preserveScroll: true });
        }
    }

    function runFilters(e: FormEvent) {
        e.preventDefault();
        router.get(route('donations.index'), Object.fromEntries(Object.entries(filterValues).filter(([, value]) => value !== '')), { preserveState: true });
    }

    function clearFilters() {
        setFilterValues({ search: '', from: '', to: '', cost_center_id: '', currency: '', payment_method: '', status: '', created_by: '' });
        router.get(route('donations.index'), {}, { preserveState: true });
    }

    const referenceLabel =
        form.data.payment_method === 'pos' ? 'POS transaction reference (optional)' : form.data.payment_method === 'bank_transfer' ? 'Bank transfer reference (optional)' : 'Cash receipt number (optional)';

    return (
        <>
            <Head title="Donations" />

            <PageHeader
                title="Donations"
                subtitle="Record what donors give, post it to the ledger at the right center, and print the donor’s receipt."
                action={
                    canCreate && (
                        <Button leadingIcon={<Plus size={16} />} onClick={openCreate}>
                            Record Donation
                        </Button>
                    )
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <FilterPanel active={hasFilters}>
                        <form onSubmit={runFilters} className="af-filter-bar">
                            <Input placeholder="Donor, receipt # or reference…" value={filterValues.search} onChange={(e) => setFilterValues({ ...filterValues, search: e.target.value })} style={{ maxWidth: '230px' }} />
                            <Input type="date" aria-label="From date" value={filterValues.from} onChange={(e) => setFilterValues({ ...filterValues, from: e.target.value })} />
                            <Input type="date" aria-label="To date" value={filterValues.to} onChange={(e) => setFilterValues({ ...filterValues, to: e.target.value })} />
                            <Select aria-label="Center" value={filterValues.cost_center_id} onChange={(e) => setFilterValues({ ...filterValues, cost_center_id: e.target.value })}>
                                <option value="">All centers</option>
                                {options.costCenters.map((center) => (
                                    <option key={center.id} value={center.id}>
                                        {center.name}
                                    </option>
                                ))}
                            </Select>
                            <Select aria-label="Currency" value={filterValues.currency} onChange={(e) => setFilterValues({ ...filterValues, currency: e.target.value })}>
                                <option value="">All currencies</option>
                                {options.currencies.map((currency) => (
                                    <option key={currency.code} value={currency.code}>
                                        {currency.code}
                                    </option>
                                ))}
                            </Select>
                            <Select aria-label="Payment method" value={filterValues.payment_method} onChange={(e) => setFilterValues({ ...filterValues, payment_method: e.target.value })}>
                                <option value="">All methods</option>
                                {options.paymentMethods.map((method) => (
                                    <option key={method.value} value={method.value}>
                                        {method.label}
                                    </option>
                                ))}
                            </Select>
                            <Select aria-label="Status" value={filterValues.status} onChange={(e) => setFilterValues({ ...filterValues, status: e.target.value })}>
                                <option value="">All statuses</option>
                                <option value="received">Received</option>
                                <option value="cancelled">Cancelled</option>
                            </Select>
                            <Select aria-label="Recorded by" value={filterValues.created_by} onChange={(e) => setFilterValues({ ...filterValues, created_by: e.target.value })}>
                                <option value="">Anyone</option>
                                {options.creators.map((user) => (
                                    <option key={user.id} value={user.id}>
                                        {user.name}
                                    </option>
                                ))}
                            </Select>
                            <Button type="submit" variant="outline">
                                Apply
                            </Button>
                            {hasFilters && (
                                <Button type="button" variant="ghost" onClick={clearFilters}>
                                    Clear
                                </Button>
                            )}
                        </form>
                    </FilterPanel>
                </div>

                {donations.data.length === 0 ? (
                    <EmptyState
                        icon={<HeartHandshake size={20} />}
                        title="No donations yet"
                        description="Record the first donation to see it here with its receipt."
                        action={canCreate && <Button onClick={openCreate}>Record Donation</Button>}
                    />
                ) : (
                    <Table cards>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Receipt</Table.HeadCell>
                            <Table.HeadCell>Donor</Table.HeadCell>
                            <Table.HeadCell>Center</Table.HeadCell>
                            <Table.HeadCell>Amount</Table.HeadCell>
                            <Table.HeadCell>Method</Table.HeadCell>
                            <Table.HeadCell>Recorded by</Table.HeadCell>
                            <Table.HeadCell>Status</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Actions</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {donations.data.map((donation) => (
                                <Table.Row key={donation.id}>
                                    <Table.Cell className="ps-3" label="Receipt">
                                        <div style={{ fontWeight: 600 }}>#{donation.receipt_number}</div>
                                        <div style={{ color: 'var(--af-label)', fontSize: '12px' }}>{donation.donated_at}</div>
                                    </Table.Cell>
                                    <Table.Cell label="Donor">{donation.donor_name ?? <span style={{ color: 'var(--af-label)' }}>ابناء الانبا رويس</span>}</Table.Cell>
                                    <Table.Cell label="Center">{donation.cost_center_name ?? '—'}</Table.Cell>
                                    <Table.Cell label="Amount">
                                        <MoneyDisplay amount={donation.amount} currency={donation.currency} />
                                    </Table.Cell>
                                    <Table.Cell label="Method">
                                        {donation.payment_method_label}
                                        {donation.payment_reference && <span style={{ color: 'var(--af-label)', fontSize: '12px' }}> · ref {donation.payment_reference}</span>}
                                    </Table.Cell>
                                    <Table.Cell label="Recorded by">{donation.created_by_name ?? '—'}</Table.Cell>
                                    <Table.Cell label="Status">
                                        <Badge variant={donation.status === 'received' ? 'success' : 'neutral'}>{donation.status_label}</Badge>
                                    </Table.Cell>
                                    <Table.Cell className="text-end pe-3" label="Actions">
                                        <div className="d-flex justify-content-end gap-1">
                                            {donation.can_print && (
                                                <>
                                                    <a
                                                        className="btn btn-sm p-1"
                                                        style={{ color: 'var(--af-primary)' }}
                                                        href={route('donations.receipt', { donation: donation.id, inline: 1 })}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        aria-label="Print receipt"
                                                        title="Print receipt"
                                                    >
                                                        <Printer size={16} />
                                                    </a>
                                                    <a className="btn btn-sm p-1" style={{ color: 'var(--af-label)' }} href={route('donations.receipt', donation.id)} aria-label="Download receipt PDF" title="Download receipt PDF">
                                                        <FileDown size={16} />
                                                    </a>
                                                </>
                                            )}
                                            {donation.can_cancel && (
                                                <button type="button" className="btn btn-sm p-1" style={{ color: 'var(--af-danger)' }} onClick={() => cancelDonation(donation)} aria-label="Cancel donation" title="Cancel donation">
                                                    <XCircle size={16} />
                                                </button>
                                            )}
                                        </div>
                                    </Table.Cell>
                                </Table.Row>
                            ))}
                        </tbody>
                    </Table>
                )}

                <div className="pagination">
                    <span>
                        Showing {from}–{to} of {donations.total} donations
                    </span>
                    <div>
                        {donations.links.map((link, index) => (
                            <Link key={index} href={link.url ?? '#'} preserveState className={`${link.active ? 'active' : ''} ${link.url ? '' : 'disabled'}`}>
                                <span dangerouslySetInnerHTML={{ __html: link.label }} />
                            </Link>
                        ))}
                    </div>
                </div>
            </Card>

            <Modal
                open={modalOpen}
                onClose={() => setModalOpen(false)}
                title="Record Donation"
                footer={
                    <>
                        <Button variant="outline" onClick={() => setModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button variant="outline" leadingIcon={<Printer size={16} />} onClick={(e) => submit(e, true)} loading={form.processing} disabled={form.data.cost_center_id === '' || form.data.amount === ''}>
                            Record & print
                        </Button>
                        <Button onClick={submit} loading={form.processing} disabled={form.data.cost_center_id === '' || form.data.amount === ''}>
                            Record donation
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="d-flex flex-column gap-3">
                    {formError && <div style={{ color: 'var(--af-danger)', fontSize: '13px' }}>{formError}</div>}

                    <div className="d-flex gap-2 align-items-end">
                        <div style={{ flex: 1 }}>
                            <SearchableSelect
                                label="Donor"
                                help="Who gave the donation. Type part of a name or phone number to find them. Leave it on Anonymous if the donor wants to stay unnamed or you don’t have their name."
                                options={options.donors.map((donor) => ({ id: donor.id, label: donor.name, detail: donor.phone }))}
                                value={form.data.donor_id === '' ? null : form.data.donor_id}
                                onChange={(id) => form.setData('donor_id', id ?? '')}
                                emptyLabel="ابناء الانبا رويس"
                                placeholder="Search donors…"
                                noResultsLabel="No donor matches — add them with New donor."
                                error={form.errors.donor_id}
                            />
                        </div>
                        <Button type="button" variant="outline" leadingIcon={<UserPlus size={16} />} onClick={() => setDonorModalOpen(true)}>
                            New donor
                        </Button>
                    </div>

                    <Select
                        label="Center"
                        help="The center this donation belongs to. It is posted to this center in the books and printed on the receipt."
                        value={form.data.cost_center_id}
                        onChange={(e) => form.setData('cost_center_id', e.target.value ? Number(e.target.value) : '')}
                        error={form.errors.cost_center_id}
                    >
                        <option value="">Select a center</option>
                        {options.costCenters.map((center) => (
                            <option key={center.id} value={center.id}>
                                {center.name}
                            </option>
                        ))}
                    </Select>

                    <div className="d-flex gap-3">
                        <Input
                            type="number"
                            step="0.01"
                            min="0.01"
                            label="Amount"
                            help="How much was donated, in the currency chosen next to it."
                            value={form.data.amount}
                            onChange={(e) => form.setData('amount', e.target.value)}
                            error={form.errors.amount}
                        />
                        <Select
                            label="Currency"
                            help="The currency the donor gave in. The receipt shows it, and the books record the value in your base currency."
                            value={form.data.currency}
                            onChange={(e) => form.setData('currency', e.target.value)}
                            error={form.errors.currency}
                        >
                            {options.currencies.map((currency) => (
                                <option key={currency.code} value={currency.code}>
                                    {currency.code}
                                </option>
                            ))}
                        </Select>
                    </div>

                    {isForeign && (
                        <Input
                            type="number"
                            step="0.0001"
                            min="0"
                            label={`Exchange rate to ${options.baseCurrency} (optional)`}
                            help={`How many ${options.baseCurrency} one ${form.data.currency} is worth. Leave it blank to use today’s rate when one is available.`}
                            value={form.data.exchange_rate}
                            onChange={(e) => form.setData('exchange_rate', e.target.value)}
                            error={form.errors.exchange_rate}
                        />
                    )}

                    <Input
                        type="date"
                        label="Date"
                        help="The day the donation was received."
                        max={today()}
                        value={form.data.donated_at}
                        onChange={(e) => form.setData('donated_at', e.target.value)}
                        error={form.errors.donated_at}
                    />

                    <div>
                        <div className="d-block" style={{ fontSize: '12px', fontWeight: 600, color: '#5e6b80', marginBottom: '6px' }}>
                            Payment method
                            <span className="ms-1">
                                <InfoTip label="About Payment method">How the donor paid. The money is recorded in the account your finance team set for this method.</InfoTip>
                            </span>
                        </div>
                        <div className="d-flex gap-2 flex-wrap">
                            {options.paymentMethods.map((method) => (
                                <Button
                                    key={method.value}
                                    type="button"
                                    variant={form.data.payment_method === method.value ? 'primary' : 'outline'}
                                    leadingIcon={METHOD_ICONS[method.value]}
                                    aria-pressed={form.data.payment_method === method.value}
                                    onClick={() => form.setData('payment_method', method.value)}
                                    style={{ flex: 1 }}
                                >
                                    {method.label}
                                </Button>
                            ))}
                        </div>
                        {form.errors.payment_method && <div style={{ color: 'var(--af-danger)', fontSize: '12px', marginTop: '4px' }}>{form.errors.payment_method}</div>}
                    </div>

                    <Input
                        label={referenceLabel}
                        help="The receipt, POS transaction or bank transfer number, so the payment can be matched to the slip or bank statement later."
                        maxLength={100}
                        value={form.data.payment_reference}
                        onChange={(e) => form.setData('payment_reference', e.target.value)}
                        error={form.errors.payment_reference}
                    />

                    <Input label="Notes (optional)" help="Anything worth remembering about this donation." value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} error={form.errors.notes} />
                </form>
            </Modal>

            <Modal
                open={donorModalOpen}
                onClose={() => setDonorModalOpen(false)}
                title="New Donor"
                footer={
                    <>
                        <Button variant="outline" onClick={() => setDonorModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={submitDonor} loading={donorForm.processing} disabled={donorForm.data.name.trim() === ''}>
                            Add donor
                        </Button>
                    </>
                }
            >
                <form onSubmit={submitDonor} className="d-flex flex-column gap-3">
                    <Input label="Name" help="The donor’s full name as it should appear on their receipt." value={donorForm.data.name} onChange={(e) => donorForm.setData('name', e.target.value)} error={donorForm.errors.name} />
                    <Input label="Phone (optional)" help="A number to reach the donor on. You can leave it blank." value={donorForm.data.phone} onChange={(e) => donorForm.setData('phone', e.target.value)} error={donorForm.errors.phone} />
                    <Input label="Email (optional)" help="An email address for the donor. You can leave it blank." type="email" value={donorForm.data.email} onChange={(e) => donorForm.setData('email', e.target.value)} error={donorForm.errors.email} />
                </form>
            </Modal>
        </>
    );
}
