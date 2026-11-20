import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeftRight, Check, HandCoins, Landmark, Plus, Undo2, Vault, XCircle } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { useConfirm } from '@/Components/ui/ConfirmProvider';
import { EmptyState } from '@/Components/ui/EmptyState';
import { FilterPanel } from '@/Components/ui/FilterPanel';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { StatCard } from '@/Components/ui/StatCard';
import { Table } from '@/Components/ui/Table';
import type { Variant } from '@/theme';
import type { TreasuryFilters, TreasuryOptions, TreasurySummary, TreasuryTransfer, TreasuryTransferStatus, TreasuryTransfersPage } from '@/types/treasury';

interface Props {
    summary: TreasurySummary;
    transfers: TreasuryTransfersPage;
    filters: TreasuryFilters;
    options: TreasuryOptions;
    canRequest: boolean;
    canManage: boolean;
}

const today = () => new Date().toISOString().slice(0, 10);

const STATUS_VARIANT: Record<TreasuryTransferStatus, Variant> = {
    requested: 'warning',
    accepted: 'success',
    rejected: 'neutral',
    voided: 'neutral',
};

const REQUEST_TYPES = [
    { value: 'deposit', label: 'Deposit with the treasurer', help: 'You hand money to the treasurer to keep for you. Nothing is posted until the treasurer confirms they received it.' },
    { value: 'withdrawal', label: 'Ask for money back', help: 'You ask the treasurer to give back money they hold for you. You can only ask for up to what the treasury holds for you.' },
    { value: 'repayment', label: 'Repay funding', help: 'You return money the treasurer funded you with. You can only repay up to what you still owe.' },
] as const;

type Modals = 'request' | 'fund' | 'move' | null;

export default function TreasuryIndex({ summary, transfers, filters, options, canRequest, canManage }: Props) {
    const confirm = useConfirm();
    const [modal, setModal] = useState<Modals>(null);
    const [formError, setFormError] = useState<string | null>(null);
    const [acceptTarget, setAcceptTarget] = useState<TreasuryTransfer | null>(null);
    const [rejectTarget, setRejectTarget] = useState<TreasuryTransfer | null>(null);
    const [filterValues, setFilterValues] = useState({
        search: filters.search ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
        type: filters.type ?? '',
        status: filters.status ?? '',
        tenant_id: filters.tenant_id ? String(filters.tenant_id) : '',
    });

    const defaultAccount = options.defaultAccountId ?? options.accounts[0]?.id ?? '';
    const firstLocation = options.locations[0]?.id ?? '';

    const requestForm = useForm({ type: 'deposit', amount: '', transfer_date: today(), tenant_account_id: defaultAccount as number | '', reference: '', notes: '' });
    const fundForm = useForm({ tenant_id: '' as number | '', amount: '', transfer_date: today(), treasury_account_id: firstLocation as number | '', reference: '', notes: '' });
    const moveForm = useForm({ amount: '', transfer_date: today(), treasury_account_id: firstLocation as number | '', counter_account_id: (options.locations[1]?.id ?? '') as number | '', reference: '', notes: '' });
    const acceptForm = useForm({ accepted_amount: '', treasury_account_id: firstLocation as number | '' });
    const rejectForm = useForm({ reason: '' });

    const hasFilters = Object.values(filterValues).some((value) => value !== '');
    const from = transfers.total === 0 ? 0 : (transfers.current_page - 1) * transfers.per_page + 1;
    const to = Math.min(transfers.current_page * transfers.per_page, transfers.total);

    /** Posting can be refused (missing mapping, not enough cash…): the refusal arrives as a flash error, so keep the modal open on it. */
    function onDone(close: () => void, reset: () => void) {
        return {
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page: { props: Record<string, unknown> }) => {
                const flash = page.props.flash as { error?: string } | undefined;

                if (flash?.error) {
                    setFormError(flash.error);
                } else {
                    close();
                    reset();
                }
            },
        };
    }

    function open(next: Modals) {
        setFormError(null);
        requestForm.clearErrors();
        fundForm.clearErrors();
        moveForm.clearErrors();
        setModal(next);
    }

    function submitRequest(e: FormEvent) {
        e.preventDefault();
        setFormError(null);
        requestForm.post(route('treasury.request'), onDone(() => setModal(null), () => requestForm.reset('amount', 'reference', 'notes')));
    }

    function submitFund(e: FormEvent) {
        e.preventDefault();
        setFormError(null);
        fundForm.post(route('treasury.fund'), onDone(() => setModal(null), () => fundForm.reset('amount', 'reference', 'notes')));
    }

    function submitMove(e: FormEvent) {
        e.preventDefault();
        setFormError(null);
        moveForm.post(route('treasury.move'), onDone(() => setModal(null), () => moveForm.reset('amount', 'reference', 'notes')));
    }

    function openAccept(transfer: TreasuryTransfer) {
        setFormError(null);
        acceptForm.setData({ accepted_amount: transfer.requested_amount, treasury_account_id: firstLocation });
        setAcceptTarget(transfer);
    }

    function submitAccept(e: FormEvent) {
        e.preventDefault();
        if (!acceptTarget) return;
        setFormError(null);
        acceptForm.post(route('treasury.accept', acceptTarget.id), onDone(() => setAcceptTarget(null), () => undefined));
    }

    function submitReject(e: FormEvent) {
        e.preventDefault();
        if (!rejectTarget) return;
        rejectForm.post(route('treasury.reject', rejectTarget.id), {
            preserveScroll: true,
            onSuccess: () => {
                setRejectTarget(null);
                rejectForm.reset();
            },
        });
    }

    async function voidTransfer(transfer: TreasuryTransfer) {
        const confirmed = await confirm(`Void ${transfer.type_label.toLowerCase()} #${transfer.id}? Its entries are reversed in both ledgers.`, { variant: 'danger', confirmLabel: 'Void transfer' });

        if (confirmed) router.post(route('treasury.void', transfer.id), {}, { preserveScroll: true });
    }

    function runFilters(e: FormEvent) {
        e.preventDefault();
        router.get(route('treasury.index'), Object.fromEntries(Object.entries(filterValues).filter(([, value]) => value !== '')), { preserveState: true });
    }

    function clearFilters() {
        setFilterValues({ search: '', from: '', to: '', type: '', status: '', tenant_id: '' });
        router.get(route('treasury.index'), {}, { preserveState: true });
    }

    const locationSelect = (value: number | '', onChange: (id: number) => void, label: string, help: string, error?: string) => (
        <Select label={label} help={help} value={value} onChange={(e) => onChange(Number(e.target.value))} error={error}>
            {options.locations.map((location) => (
                <option key={location.id} value={location.id}>
                    {location.code} {location.name} — {location.balance.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                </option>
            ))}
        </Select>
    );

    const selectedRequestType = REQUEST_TYPES.find((type) => type.value === requestForm.data.type);

    return (
        <>
            <Head title="Treasury" />

            <PageHeader
                title="Treasury"
                subtitle={canManage ? 'The treasurer’s safe and banks: accept what tenants hand over, fund tenants, and move money between cash and bank.' : 'Money you have handed to the treasurer, and what you can still ask back.'}
                action={
                    <div className="d-flex gap-2 flex-wrap">
                        {canRequest && (
                            <Button leadingIcon={<Plus size={16} />} onClick={() => open('request')}>
                                New request
                            </Button>
                        )}
                        {canManage && (
                            <>
                                <Button variant="outline" leadingIcon={<HandCoins size={16} />} onClick={() => open('fund')}>
                                    Fund a tenant
                                </Button>
                                <Button variant="outline" leadingIcon={<ArrowLeftRight size={16} />} onClick={() => open('move')}>
                                    Cash ↔ bank
                                </Button>
                            </>
                        )}
                    </div>
                }
            />

            {summary.mode === 'tenant' && (
                <div className="row g-3 mb-3">
                    <div className="col-md-6">
                        <StatCard label="Held for you by the treasurer" value={summary.held.toLocaleString(undefined, { minimumFractionDigits: 2 })} sub="You can ask for this back" icon={<Vault size={18} />} />
                    </div>
                    <div className="col-md-6">
                        <StatCard label="You owe the treasury" value={summary.owed.toLocaleString(undefined, { minimumFractionDigits: 2 })} sub="Funding to repay" icon={<HandCoins size={18} />} color={summary.owed > 0 ? 'warning' : 'primary'} />
                    </div>
                </div>
            )}

            {summary.mode === 'treasury' && (
                <div className="row g-3 mb-3">
                    <div className="col-lg-5">
                        <Card padded={false}>
                            <div className="p-3 fw-semibold">Safe and banks</div>
                            <Table>
                                <Table.Head>
                                    <Table.HeadCell className="ps-3">Account</Table.HeadCell>
                                    <Table.HeadCell className="text-end pe-3">Balance</Table.HeadCell>
                                </Table.Head>
                                <tbody>
                                    {summary.locations.map((location) => (
                                        <Table.Row key={location.id}>
                                            <Table.Cell className="ps-3">
                                                <Landmark size={14} className="me-2" />
                                                {location.code} {location.name}
                                            </Table.Cell>
                                            <Table.Cell className="text-end pe-3">
                                                <MoneyDisplay amount={location.balance} currency={location.currency ?? ''} />
                                            </Table.Cell>
                                        </Table.Row>
                                    ))}
                                </tbody>
                            </Table>
                        </Card>
                    </div>
                    <div className="col-lg-7">
                        <Card padded={false}>
                            <div className="p-3 fw-semibold">Per tenant</div>
                            {summary.positions.length === 0 ? (
                                <EmptyState icon={<Vault size={20} />} title="Nothing held yet" description="Once a deposit is accepted, what the treasury holds for each tenant shows here." />
                            ) : (
                                <Table>
                                    <Table.Head>
                                        <Table.HeadCell className="ps-3">Tenant</Table.HeadCell>
                                        <Table.HeadCell>Held for tenant</Table.HeadCell>
                                        <Table.HeadCell>Owed by tenant</Table.HeadCell>
                                        <Table.HeadCell className="pe-3">Tenant’s books</Table.HeadCell>
                                    </Table.Head>
                                    <tbody>
                                        {summary.positions.map((position) => (
                                            <Table.Row key={position.tenant_id}>
                                                <Table.Cell className="ps-3">{position.tenant_name}</Table.Cell>
                                                <Table.Cell>
                                                    <MoneyDisplay amount={position.held} currency="" />
                                                </Table.Cell>
                                                <Table.Cell>
                                                    <MoneyDisplay amount={position.advanced} currency="" />
                                                </Table.Cell>
                                                <Table.Cell className="pe-3">
                                                    <Badge variant={position.matches ? 'success' : 'danger'}>{position.matches ? 'Agrees' : 'Does not agree'}</Badge>
                                                </Table.Cell>
                                            </Table.Row>
                                        ))}
                                    </tbody>
                                </Table>
                            )}
                        </Card>
                    </div>
                </div>
            )}

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <FilterPanel active={hasFilters}>
                        <form onSubmit={runFilters} className="af-filter-bar">
                            <Input placeholder="Reference or notes…" value={filterValues.search} onChange={(e) => setFilterValues({ ...filterValues, search: e.target.value })} style={{ maxWidth: '220px' }} />
                            <Input type="date" aria-label="From date" value={filterValues.from} onChange={(e) => setFilterValues({ ...filterValues, from: e.target.value })} />
                            <Input type="date" aria-label="To date" value={filterValues.to} onChange={(e) => setFilterValues({ ...filterValues, to: e.target.value })} />
                            <Select aria-label="Type" value={filterValues.type} onChange={(e) => setFilterValues({ ...filterValues, type: e.target.value })}>
                                <option value="">All types</option>
                                {options.types.map((type) => (
                                    <option key={type.value} value={type.value}>
                                        {type.label}
                                    </option>
                                ))}
                            </Select>
                            <Select aria-label="Status" value={filterValues.status} onChange={(e) => setFilterValues({ ...filterValues, status: e.target.value })}>
                                <option value="">All statuses</option>
                                {options.statuses.map((status) => (
                                    <option key={status.value} value={status.value}>
                                        {status.label}
                                    </option>
                                ))}
                            </Select>
                            {canManage && (
                                <Select aria-label="Tenant" value={filterValues.tenant_id} onChange={(e) => setFilterValues({ ...filterValues, tenant_id: e.target.value })}>
                                    <option value="">All tenants</option>
                                    {options.tenants.map((tenant) => (
                                        <option key={tenant.id} value={tenant.id}>
                                            {tenant.name}
                                        </option>
                                    ))}
                                </Select>
                            )}
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

                {transfers.data.length === 0 ? (
                    <EmptyState icon={<Vault size={20} />} title="No transfers yet" description={canRequest ? 'Send your first request to hand money to the treasurer.' : 'Requests and movements show up here.'} />
                ) : (
                    <Table cards>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Transfer</Table.HeadCell>
                            <Table.HeadCell>Tenant</Table.HeadCell>
                            <Table.HeadCell>Amount</Table.HeadCell>
                            <Table.HeadCell>Accounts</Table.HeadCell>
                            <Table.HeadCell>Status</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Actions</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {transfers.data.map((transfer) => (
                                <Table.Row key={transfer.id}>
                                    <Table.Cell className="ps-3" label="Transfer">
                                        <div style={{ fontWeight: 600 }}>
                                            {transfer.type_label} #{transfer.id}
                                        </div>
                                        <div style={{ color: 'var(--af-label)', fontSize: '12px' }}>
                                            {transfer.transfer_date}
                                            {transfer.reference && ` · ref ${transfer.reference}`}
                                        </div>
                                    </Table.Cell>
                                    <Table.Cell label="Tenant">{transfer.tenant_name ?? '—'}</Table.Cell>
                                    <Table.Cell label="Amount">
                                        <MoneyDisplay amount={transfer.accepted_amount ?? transfer.requested_amount} currency="" />
                                        {transfer.accepted_amount !== null && transfer.accepted_amount !== transfer.requested_amount && <div style={{ color: 'var(--af-label)', fontSize: '12px' }}>asked {transfer.requested_amount}</div>}
                                    </Table.Cell>
                                    <Table.Cell label="Accounts">
                                        <div style={{ fontSize: '12.5px' }}>
                                            {transfer.tenant_account && <div>Tenant: {transfer.tenant_account}</div>}
                                            {transfer.treasury_account && <div>Treasury: {transfer.treasury_account}</div>}
                                            {transfer.counter_account && <div>→ {transfer.counter_account}</div>}
                                            {!transfer.tenant_account && !transfer.treasury_account && '—'}
                                        </div>
                                    </Table.Cell>
                                    <Table.Cell label="Status">
                                        <span title={transfer.reject_reason ?? undefined}>
                                            <Badge variant={STATUS_VARIANT[transfer.status]}>{transfer.status_label}</Badge>
                                        </span>
                                    </Table.Cell>
                                    <Table.Cell className="text-end pe-3" label="Actions">
                                        <div className="d-flex justify-content-end gap-1">
                                            {transfer.can_decide && (
                                                <>
                                                    <button type="button" className="btn btn-sm p-1" style={{ color: 'var(--af-primary)' }} onClick={() => openAccept(transfer)} aria-label="Accept" title="Accept">
                                                        <Check size={16} />
                                                    </button>
                                                    <button type="button" className="btn btn-sm p-1" style={{ color: 'var(--af-label)' }} onClick={() => setRejectTarget(transfer)} aria-label="Reject" title="Reject">
                                                        <XCircle size={16} />
                                                    </button>
                                                </>
                                            )}
                                            {transfer.can_void && (
                                                <button type="button" className="btn btn-sm p-1" style={{ color: 'var(--af-danger)' }} onClick={() => voidTransfer(transfer)} aria-label="Void" title="Void">
                                                    <Undo2 size={16} />
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
                        Showing {from}–{to} of {transfers.total} transfers
                    </span>
                    <div>
                        {transfers.links.map((link, index) => (
                            <Link key={index} href={link.url ?? '#'} preserveState className={`${link.active ? 'active' : ''} ${link.url ? '' : 'disabled'}`}>
                                <span dangerouslySetInnerHTML={{ __html: link.label }} />
                            </Link>
                        ))}
                    </div>
                </div>
            </Card>

            <Modal
                open={modal === 'request'}
                onClose={() => setModal(null)}
                title="New treasury request"
                footer={
                    <>
                        <Button variant="outline" onClick={() => setModal(null)}>
                            Cancel
                        </Button>
                        <Button onClick={submitRequest} loading={requestForm.processing} disabled={requestForm.data.amount === ''}>
                            Send request
                        </Button>
                    </>
                }
            >
                <form onSubmit={submitRequest} className="d-flex flex-column gap-3">
                    <button type="submit" hidden tabIndex={-1} aria-hidden="true" />
                    {formError && <div style={{ color: 'var(--af-danger)', fontSize: '13px' }}>{formError}</div>}
                    <Select label="What do you want to do?" help={selectedRequestType?.help} value={requestForm.data.type} onChange={(e) => requestForm.setData('type', e.target.value)} error={requestForm.errors.type}>
                        {REQUEST_TYPES.map((type) => (
                            <option key={type.value} value={type.value}>
                                {type.label}
                            </option>
                        ))}
                    </Select>
                    <Input type="number" step="0.01" min="0.01" label="Amount" help="How much money is being handed over, asked back or repaid." value={requestForm.data.amount} onChange={(e) => requestForm.setData('amount', e.target.value)} error={requestForm.errors.amount} />
                    <Select
                        label={requestForm.data.type === 'deposit' || requestForm.data.type === 'repayment' ? 'Money comes out of' : 'Money goes into'}
                        help="Your own cash or bank account. A deposit or repayment takes money out of it; a withdrawal puts money into it. It is only posted once the treasurer accepts."
                        value={requestForm.data.tenant_account_id}
                        onChange={(e) => requestForm.setData('tenant_account_id', Number(e.target.value))}
                        error={requestForm.errors.tenant_account_id}
                    >
                        {options.accounts.map((account) => (
                            <option key={account.id} value={account.id}>
                                {account.label}
                            </option>
                        ))}
                    </Select>
                    <Input type="date" label="Date" help="The day the money is, or will be, handed over." max={today()} value={requestForm.data.transfer_date} onChange={(e) => requestForm.setData('transfer_date', e.target.value)} error={requestForm.errors.transfer_date} />
                    <Input label="Reference (optional)" help="A slip or receipt number so the treasurer can match this to what they counted." maxLength={100} value={requestForm.data.reference} onChange={(e) => requestForm.setData('reference', e.target.value)} error={requestForm.errors.reference} />
                    <Input label="Notes (optional)" help="Anything the treasurer should know about this request." value={requestForm.data.notes} onChange={(e) => requestForm.setData('notes', e.target.value)} error={requestForm.errors.notes} />
                </form>
            </Modal>

            <Modal
                open={modal === 'fund'}
                onClose={() => setModal(null)}
                title="Fund a tenant"
                footer={
                    <>
                        <Button variant="outline" onClick={() => setModal(null)}>
                            Cancel
                        </Button>
                        <Button onClick={submitFund} loading={fundForm.processing} disabled={fundForm.data.amount === '' || fundForm.data.tenant_id === ''}>
                            Fund tenant
                        </Button>
                    </>
                }
            >
                <form onSubmit={submitFund} className="d-flex flex-column gap-3">
                    <button type="submit" hidden tabIndex={-1} aria-hidden="true" />
                    {formError && <div style={{ color: 'var(--af-danger)', fontSize: '13px' }}>{formError}</div>}
                    <Select label="Tenant" help="Who receives the money. It arrives in the cash account set as “Cash account for treasury transfers” in their General Ledger mapping." value={fundForm.data.tenant_id} onChange={(e) => fundForm.setData('tenant_id', e.target.value ? Number(e.target.value) : '')} error={fundForm.errors.tenant_id}>
                        <option value="">Select a tenant</option>
                        {options.tenants.map((tenant) => (
                            <option key={tenant.id} value={tenant.id}>
                                {tenant.name}
                            </option>
                        ))}
                    </Select>
                    {locationSelect(fundForm.data.treasury_account_id, (id) => fundForm.setData('treasury_account_id', id), 'Paid out of', 'The safe or bank the money leaves. It can be any tenant’s deposit — funding is an advance the receiving tenant owes back, so no depositor’s balance changes.', fundForm.errors.treasury_account_id)}
                    <Input type="number" step="0.01" min="0.01" label="Amount" help="How much the tenant receives. The safe or bank must hold at least this much." value={fundForm.data.amount} onChange={(e) => fundForm.setData('amount', e.target.value)} error={fundForm.errors.amount} />
                    <Input type="date" label="Date" help="The day the money was handed over." max={today()} value={fundForm.data.transfer_date} onChange={(e) => fundForm.setData('transfer_date', e.target.value)} error={fundForm.errors.transfer_date} />
                    <Input label="Reference (optional)" help="A slip number or note to find this funding later." maxLength={100} value={fundForm.data.reference} onChange={(e) => fundForm.setData('reference', e.target.value)} error={fundForm.errors.reference} />
                </form>
            </Modal>

            <Modal
                open={modal === 'move'}
                onClose={() => setModal(null)}
                title="Move between cash and bank"
                footer={
                    <>
                        <Button variant="outline" onClick={() => setModal(null)}>
                            Cancel
                        </Button>
                        <Button onClick={submitMove} loading={moveForm.processing} disabled={moveForm.data.amount === ''}>
                            Move money
                        </Button>
                    </>
                }
            >
                <form onSubmit={submitMove} className="d-flex flex-column gap-3">
                    <button type="submit" hidden tabIndex={-1} aria-hidden="true" />
                    {formError && <div style={{ color: 'var(--af-danger)', fontSize: '13px' }}>{formError}</div>}
                    {locationSelect(moveForm.data.treasury_account_id, (id) => moveForm.setData('treasury_account_id', id), 'From', 'The safe or bank the money leaves, for example the safe when you deposit cash in the bank.', moveForm.errors.treasury_account_id)}
                    {locationSelect(moveForm.data.counter_account_id, (id) => moveForm.setData('counter_account_id', id), 'To', 'The safe or bank the money goes into. It must be a different account from “From”.', moveForm.errors.counter_account_id)}
                    <Input type="number" step="0.01" min="0.01" label="Amount" help="How much to move. The “From” account must hold at least this much." value={moveForm.data.amount} onChange={(e) => moveForm.setData('amount', e.target.value)} error={moveForm.errors.amount} />
                    <Input type="date" label="Date" help="The day the money was moved." max={today()} value={moveForm.data.transfer_date} onChange={(e) => moveForm.setData('transfer_date', e.target.value)} error={moveForm.errors.transfer_date} />
                    <Input label="Reference (optional)" help="A bank slip or deposit number." maxLength={100} value={moveForm.data.reference} onChange={(e) => moveForm.setData('reference', e.target.value)} error={moveForm.errors.reference} />
                </form>
            </Modal>

            <Modal
                open={acceptTarget !== null}
                onClose={() => setAcceptTarget(null)}
                title={`Accept ${acceptTarget?.type_label.toLowerCase() ?? ''}`}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setAcceptTarget(null)}>
                            Back
                        </Button>
                        <Button onClick={submitAccept} loading={acceptForm.processing}>
                            Accept and post
                        </Button>
                    </>
                }
            >
                <form onSubmit={submitAccept} className="d-flex flex-column gap-3">
                    <button type="submit" hidden tabIndex={-1} aria-hidden="true" />
                    {formError && <div style={{ color: 'var(--af-danger)', fontSize: '13px' }}>{formError}</div>}
                    <div style={{ fontSize: '13px' }}>
                        {acceptTarget?.tenant_name} asked for {acceptTarget?.requested_amount}. Accepting posts an entry in their books and one in the treasury’s.
                    </div>
                    <Input type="number" step="0.01" min="0.01" label="Amount actually received" help="What you really counted or paid. It can be less than what was asked for, never more." value={acceptForm.data.accepted_amount} onChange={(e) => acceptForm.setData('accepted_amount', e.target.value)} error={acceptForm.errors.accepted_amount} />
                    {locationSelect(
                        acceptForm.data.treasury_account_id,
                        (id) => acceptForm.setData('treasury_account_id', id),
                        acceptTarget?.type === 'withdrawal' ? 'Paid out of' : 'Goes into',
                        'The safe or bank account the money goes into, or comes out of for a withdrawal.',
                        acceptForm.errors.treasury_account_id,
                    )}
                </form>
            </Modal>

            <Modal
                open={rejectTarget !== null}
                onClose={() => setRejectTarget(null)}
                title="Reject request"
                footer={
                    <>
                        <Button variant="outline" onClick={() => setRejectTarget(null)}>
                            Back
                        </Button>
                        <Button variant="danger" onClick={submitReject} loading={rejectForm.processing}>
                            Reject request
                        </Button>
                    </>
                }
            >
                <form onSubmit={submitReject} className="d-flex flex-column gap-2">
                    <button type="submit" hidden tabIndex={-1} aria-hidden="true" />
                    <div style={{ fontSize: '13px' }}>Nothing is posted when a request is rejected.</div>
                    <Input label="Reason (optional)" help="Why the request is refused. The tenant sees it when they hover over the status." value={rejectForm.data.reason} onChange={(e) => rejectForm.setData('reason', e.target.value)} error={rejectForm.errors.reason} />
                </form>
            </Modal>
        </>
    );
}
