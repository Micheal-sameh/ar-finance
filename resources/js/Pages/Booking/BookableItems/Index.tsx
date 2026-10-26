import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarClock, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { CostCenterPicker } from '@/Components/finance/CostCenterPicker';
import { PageHeader } from '@/Components/layout/PageHeader';
import { ServiceField } from '@/Components/layout/ServiceField';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { useConfirm } from '@/Components/ui/ConfirmProvider';
import { EmptyState } from '@/Components/ui/EmptyState';
import { FilterPanel } from '@/Components/ui/FilterPanel';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { Table } from '@/Components/ui/Table';
import type { BookableItem, BookableItemsPage, BookingRateType } from '@/types/booking';
import { InfoTip } from '@/Components/ui/InfoTip';

interface BookingTypeRow {
    booking_type_id: number | null;
    rate_type: BookingRateType;
    price: string;
}

interface Props {
    items: BookableItemsPage;
    bookingTypeOptions: { id: number; name: string; tenant_id: number }[];
    filters: { search?: string };
    viewingAllTenants: boolean;
    canManage: boolean;
}

export default function BookableItemsIndex({ items, bookingTypeOptions, filters, viewingAllTenants, canManage }: Props) {
    const canWrite = canManage;
    const confirm = useConfirm();
    const [search, setSearch] = useState(filters.search ?? '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<BookableItem | null>(null);
    const [serviceId, setServiceId] = useState<number | null>(null);
    const formTenantId = editing ? ((editing as { tenant_id?: number }).tenant_id ?? null) : serviceId;
    const typeOptions = viewingAllTenants ? bookingTypeOptions.filter((option) => option.tenant_id === formTenantId) : bookingTypeOptions;

    const form = useForm<{
        name: string;
        description: string;
        is_exclusive: boolean;
        deposit_percent: string;
        account_id: number | null;
        deferred_revenue_account_id: number | null;
        deposit_payment_account_id: number | null;
        cost_center_id: number | null;
        booking_types: BookingTypeRow[];
    }>({
        name: '',
        description: '',
        is_exclusive: true,
        deposit_percent: '',
        account_id: null,
        deferred_revenue_account_id: null,
        deposit_payment_account_id: null,
        cost_center_id: null,
        booking_types: [],
    });

    function openCreate() {
        setEditing(null);
        setServiceId(null);
        form.reset();
        form.clearErrors();
        setModalOpen(true);
    }

    function openEdit(item: BookableItem) {
        setEditing(item);
        form.setData({
            name: item.name,
            description: item.description ?? '',
            is_exclusive: item.is_exclusive,
            deposit_percent: item.deposit_percent ?? '',
            account_id: item.account_id,
            deferred_revenue_account_id: item.deferred_revenue_account_id,
            deposit_payment_account_id: item.deposit_payment_account_id,
            cost_center_id: item.cost_center_id,
            booking_types: (item.booking_types ?? []).map((type) => ({ booking_type_id: type.id, rate_type: type.rate_type, price: type.price })),
        });
        form.clearErrors();
        setModalOpen(true);
    }

    function updateType(index: number, changes: Partial<BookingTypeRow>) {
        form.setData(
            'booking_types',
            form.data.booking_types.map((type, i) => (i === index ? { ...type, ...changes } : type)),
        );
    }

    function removeType(index: number) {
        form.setData(
            'booking_types',
            form.data.booking_types.filter((_, i) => i !== index),
        );
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        form.transform((data) => (editing ? data : { ...data, for_tenant: serviceId }));

        if (editing) {
            form.put(route('bookable-items.update', editing.id), { onSuccess: () => setModalOpen(false) });
        } else {
            form.post(route('bookable-items.store'), {
                onSuccess: () => {
                    setModalOpen(false);
                    form.reset();
                },
            });
        }
    }

    async function destroy(item: BookableItem) {
        if (await confirm(`Delete "${item.name}"?`, { variant: 'danger', confirmLabel: 'Delete' })) {
            router.delete(route('bookable-items.destroy', item.id));
        }
    }

    function runSearch(e: FormEvent) {
        e.preventDefault();
        router.get(route('bookable-items.index'), { search }, { preserveState: true });
    }

    return (
        <>
            <Head title="Bookable Items" />

            <PageHeader
                title="Bookable Items"
                subtitle={
                    viewingAllTenants
                        ? 'Every service’s bookable items, combined. Switch into a service to manage one.'
                        : 'What people can book on the calendar, its rate, and where it posts once paid.'
                }
                action={
                    canWrite && (
                        <Button leadingIcon={<Plus size={16} />} onClick={openCreate}>
                            New Item
                        </Button>
                    )
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <FilterPanel active={Boolean(filters.search)}>
                        <form onSubmit={runSearch} className="af-filter-bar">
                            <Input
                                placeholder="Search by name…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                style={{ maxWidth: '240px' }}
                            />
                            <Button type="submit" variant="outline">
                                Search
                            </Button>
                        </form>
                    </FilterPanel>
                </div>

                {items.data.length === 0 ? (
                    <EmptyState
                        icon={<CalendarClock size={20} />}
                        title="No bookable items yet"
                        description="Create something people can book — a room, a therapist, a piece of equipment — and set its rate and ledger accounts."
                        action={canWrite && <Button onClick={openCreate}>New Item</Button>}
                    />
                ) : (
                    <Table cards>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Name</Table.HeadCell>
                            <Table.HeadCell>Availability</Table.HeadCell>
                            <Table.HeadCell>Rate</Table.HeadCell>
                            <Table.HeadCell>Account</Table.HeadCell>
                            {viewingAllTenants && <Table.HeadCell>Service</Table.HeadCell>}
                            {canWrite && <Table.HeadCell className="text-end pe-3">Actions</Table.HeadCell>}
                        </Table.Head>
                        <tbody>
                            {items.data.map((item) => (
                                <Table.Row key={item.id}>
                                    <Table.Cell className="ps-3" label="Name">
                                        {item.name}
                                        {!item.is_active && (
                                            <span style={{ color: 'var(--af-label)', fontSize: '12px' }}> · inactive</span>
                                        )}
                                    </Table.Cell>
                                    <Table.Cell label="Availability">
                                        <Badge variant={item.is_exclusive ? 'warning' : 'info'}>
                                            {item.is_exclusive ? 'Exclusive' : 'Shared'}
                                        </Badge>
                                    </Table.Cell>
                                    <Table.Cell label="Rate">
                                        {item.booking_types && item.booking_types.length > 0 ? (
                                            <div className="d-flex flex-column">
                                                {item.booking_types.map((type) => (
                                                    <span key={type.id}>
                                                        {type.name}: {type.price} {type.rate_type === 'hourly' ? '/ hour' : 'flat'}
                                                    </span>
                                                ))}
                                            </div>
                                        ) : (
                                            '—'
                                        )}
                                        {item.deposit_percent && (
                                            <span style={{ color: 'var(--af-label)', fontSize: '12px' }}> · {item.deposit_percent}% deposit</span>
                                        )}
                                    </Table.Cell>
                                    <Table.Cell label="Account">{item.account?.name}</Table.Cell>
                                    {viewingAllTenants && <Table.Cell label="Service">{item.tenant_name}</Table.Cell>}
                                    {canWrite && (
                                        <Table.Cell className="text-end pe-3" label="Actions">
                                            <div className="d-flex justify-content-end gap-1">
                                                <button type="button" className="btn btn-sm p-1" style={{ color: 'var(--af-label)' }} onClick={() => openEdit(item)} aria-label="Edit">
                                                    <Pencil size={15} />
                                                </button>
                                                <button type="button" className="btn btn-sm p-1" style={{ color: 'var(--af-danger)' }} onClick={() => destroy(item)} aria-label="Delete">
                                                    <Trash2 size={15} />
                                                </button>
                                            </div>
                                        </Table.Cell>
                                    )}
                                </Table.Row>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>

            <Modal
                open={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editing ? 'Edit Item' : 'New Item'}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={submit} loading={form.processing}>
                            {editing ? 'Save changes' : 'Create item'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="d-flex flex-column gap-3">
                    {viewingAllTenants && !editing && (
                        <ServiceField value={serviceId} onChange={setServiceId} error={(form.errors as Record<string, string>).for_tenant} />
                    )}
                    <Input label="Name" help="What customers can book, for example Main Hall or Tennis Court 1." value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
                    <Input
                        label="Description (optional)"
                        help="Extra details staff may want to see when taking a booking."
                        value={form.data.description}
                        onChange={(e) => form.setData('description', e.target.value)}
                        error={form.errors.description}
                    />

                    <div className="form-check">
                        <input
                            type="checkbox"
                            className="form-check-input"
                            id="is_exclusive"
                            checked={form.data.is_exclusive}
                            onChange={(e) => form.setData('is_exclusive', e.target.checked)}
                        />
                        <label className="form-check-label" htmlFor="is_exclusive" style={{ fontSize: '13px' }}>
                            Exclusive — booking it blocks that time slot for everyone else. Leave unchecked to let any number of people book the same slot.
                        </label>
                    </div>

                    <div>
                        <div className="d-flex align-items-center justify-content-between mb-1">
                            <label style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                Booking types offered here — each with its own rate type and price.
                            </label>
                            <Button
                                type="button"
                                variant="outline"
                                leadingIcon={<Plus size={14} />}
                                onClick={() => form.setData('booking_types', [...form.data.booking_types, { booking_type_id: null, rate_type: 'hourly', price: '' }])}
                            >
                                Add type
                            </Button>
                        </div>
                        {typeOptions.length === 0 && (
                            <div style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                No booking types yet — <Link href={route('booking-types.index')}>create some first</Link>.
                            </div>
                        )}
                        <div className="d-flex flex-column gap-2">
                            {form.data.booking_types.map((row, index) => (
                                <div key={index} className="d-flex gap-2 align-items-start">
                                    <Select
                                        value={row.booking_type_id ?? ''}
                                        onChange={(e) => updateType(index, { booking_type_id: e.target.value ? Number(e.target.value) : null })}
                                        error={(form.errors as Record<string, string>)[`booking_types.${index}.booking_type_id`]}
                                    >
                                        <option value="">Select a type</option>
                                        {typeOptions.map((option) => (
                                            <option
                                                key={option.id}
                                                value={option.id}
                                                disabled={form.data.booking_types.some((other, i) => i !== index && other.booking_type_id === option.id)}
                                            >
                                                {option.name}
                                            </option>
                                        ))}
                                    </Select>
                                    <Select
                                        value={row.rate_type}
                                        onChange={(e) => updateType(index, { rate_type: e.target.value as BookingRateType })}
                                        error={(form.errors as Record<string, string>)[`booking_types.${index}.rate_type`]}
                                    >
                                        <option value="hourly">Per hour</option>
                                        <option value="flat">Flat rate</option>
                                    </Select>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        placeholder="Price"
                                        value={row.price}
                                        onChange={(e) => updateType(index, { price: e.target.value })}
                                        error={(form.errors as Record<string, string>)[`booking_types.${index}.price`]}
                                    />
                                    <button type="button" className="btn btn-sm p-1 mt-2" style={{ color: 'var(--af-danger)' }} onClick={() => removeType(index)} aria-label="Remove type">
                                        <Trash2 size={15} />
                                    </button>
                                </div>
                            ))}
                        </div>
                    </div>

                    <Input
                        type="number"
                        step="0.01"
                        min="0.01"
                        max="100"
                        label="Deposit % (optional)"
                        help="Leave blank if this item must be paid in full — otherwise the percentage of the price collectible upfront as a deposit."
                        value={form.data.deposit_percent}
                        onChange={(e) => form.setData('deposit_percent', e.target.value)}
                        error={form.errors.deposit_percent}
                    />

                    <div>
                        <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                            Revenue account <InfoTip>The income account credited once the booking has taken place.</InfoTip>
                        </label>
                        <AccountPicker
                            tenantId={formTenantId}
                            value={form.data.account_id}
                            onChange={(id) => form.setData('account_id', id)}
                            filterType="revenue"
                            error={form.errors.account_id}
                        />
                    </div>

                    {form.data.deposit_percent !== '' && (
                        <>
                            <div>
                                <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                    Deferred revenue account <InfoTip>Where money received in advance is held until the booking date, because it is not yet earned.</InfoTip>
                                </label>
                                <AccountPicker
                                    tenantId={formTenantId}
                                    value={form.data.deferred_revenue_account_id}
                                    onChange={(id) => form.setData('deferred_revenue_account_id', id)}
                                    filterType="liability"
                                    error={form.errors.deferred_revenue_account_id}
                                />
                            </div>

                            <div>
                                <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                    Deposit received into <InfoTip>The bank or cash account that receives the deposit.</InfoTip>
                                </label>
                                <AccountPicker
                                    tenantId={formTenantId}
                                    value={form.data.deposit_payment_account_id}
                                    onChange={(id) => form.setData('deposit_payment_account_id', id)}
                                    filterType="asset"
                                    error={form.errors.deposit_payment_account_id}
                                />
                            </div>
                        </>
                    )}

                    <div>
                        <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                            Cost/profit center <InfoTip>The department or project this item belongs to, for reports.</InfoTip>
                        </label>
                        <CostCenterPicker tenantId={formTenantId} value={form.data.cost_center_id} onChange={(id) => form.setData('cost_center_id', id)} error={form.errors.cost_center_id} />
                    </div>
                </form>
            </Modal>
        </>
    );
}
