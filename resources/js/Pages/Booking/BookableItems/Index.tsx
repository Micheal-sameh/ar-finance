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
import { FormSection } from '@/Components/ui/FormSection';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { Table } from '@/Components/ui/Table';
import type { BookableItem, BookableItemsPage, BookingRateType } from '@/types/booking';
import type { GlDefaults } from '@/types/finance';
import { InfoTip } from '@/Components/ui/InfoTip';

interface BookingTypeRow {
    booking_type_id: number | null;
    rate_type: BookingRateType;
    price: string;
    account_id: number | null;
    cost_center_id: number | null;
}

interface Props {
    items: BookableItemsPage;
    bookingTypeOptions: { id: number; name: string; tenant_id: number }[];
    filters: { search?: string };
    viewingAllTenants: boolean;
    canManage: boolean;
    /** Finance/admin only: may choose revenue accounts and cost centers. */
    canManageAccounting: boolean;
    /** Null when a Platform Admin views all services, since the mapping is per service. */
    glDefaults: GlDefaults | null;
}

export default function BookableItemsIndex({ items, bookingTypeOptions, filters, viewingAllTenants, canManage, canManageAccounting, glDefaults }: Props) {
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
        account_id: number | null;
        cost_center_id: number | null;
        booking_types: BookingTypeRow[];
    }>({
        name: '',
        description: '',
        is_exclusive: true,
        account_id: glDefaults?.booking_revenue ?? null,
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
            account_id: item.account_id,
            cost_center_id: item.cost_center_id,
            booking_types: (item.booking_types ?? []).map((type) => ({ booking_type_id: type.id, rate_type: type.rate_type, price: type.price, account_id: type.account_id ?? null, cost_center_id: type.cost_center_id ?? null })),
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
                            <Table.HeadCell>Accounting</Table.HeadCell>
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
                                    </Table.Cell>
                                    <Table.Cell label="Accounting">
                                        {item.account_id === null ? <Badge variant="warning">Needs setup</Badge> : canManageAccounting ? item.account?.name : <Badge variant="success">Ready</Badge>}
                                    </Table.Cell>
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
                size="lg"
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
                <form onSubmit={submit} className="d-flex flex-column gap-4">
                    {viewingAllTenants && !editing && (
                        <ServiceField value={serviceId} onChange={setServiceId} error={(form.errors as Record<string, string>).for_tenant} />
                    )}

                    <FormSection title="Details">
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
                                <strong>Exclusive</strong> — booking it blocks that time slot for everyone else. Leave unchecked to let any number of people book the same slot.
                            </label>
                        </div>
                    </FormSection>

                    <FormSection
                        title="Booking types & pricing"
                        description="What can be booked here, and at what rate."
                        action={
                            <Button
                                type="button"
                                variant="outline"
                                leadingIcon={<Plus size={14} />}
                                onClick={() => form.setData('booking_types', [...form.data.booking_types, { booking_type_id: null, rate_type: 'hourly', price: '', account_id: null, cost_center_id: null }])}
                            >
                                Add type
                            </Button>
                        }
                    >
                        {typeOptions.length === 0 && (
                            <div style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                No booking types yet — <Link href={route('booking-types.index')}>create some first</Link>.
                            </div>
                        )}
                        {form.data.booking_types.length > 0 && (
                            <div className="d-flex flex-column gap-2">
                                {form.data.booking_types.map((row, index) => (
                                    <div key={index} className="d-flex gap-2 align-items-start flex-nowrap">
                                        <div style={{ flex: '2 1 0', minWidth: 0 }}>
                                            <Select
                                                aria-label="Booking type"
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
                                        </div>
                                        <div style={{ flex: '1 1 0', minWidth: 0 }}>
                                            <Select
                                                aria-label="Rate type"
                                                value={row.rate_type}
                                                onChange={(e) => updateType(index, { rate_type: e.target.value as BookingRateType })}
                                                error={(form.errors as Record<string, string>)[`booking_types.${index}.rate_type`]}
                                            >
                                                <option value="hourly">Per hour</option>
                                                <option value="flat">Flat rate</option>
                                            </Select>
                                        </div>
                                        <div style={{ flex: '1 1 0', minWidth: 0 }}>
                                            <Input
                                                aria-label="Price"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                placeholder="Price (0 = free)"
                                                value={row.price}
                                                onChange={(e) => updateType(index, { price: e.target.value })}
                                                error={(form.errors as Record<string, string>)[`booking_types.${index}.price`]}
                                            />
                                        </div>
                                        <button type="button" className="btn btn-sm p-1 mt-2 flex-shrink-0" style={{ color: 'var(--af-danger)' }} onClick={() => removeType(index)} aria-label="Remove type">
                                            <Trash2 size={15} />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </FormSection>

                    {canManageAccounting ? (
                        <FormSection
                            title="Accounting"
                            description="Finance & admin only — booking staff don’t see these settings. Anything left empty uses the General Ledger mapping."
                            tinted
                        >
                            <div className="row g-3">
                                <div className="col-12 col-md-6">
                                    <FieldLabel tip="The income account credited once a booking has taken place. Used by every type below that has no account of its own.">Default revenue account</FieldLabel>
                                    <AccountPicker tenantId={formTenantId} value={form.data.account_id} onChange={(id) => form.setData('account_id', id)} filterType="revenue" error={form.errors.account_id} />
                                </div>
                                <div className="col-12 col-md-6">
                                    <FieldLabel tip="The department or project this item reports under. Used by every type below that has no center of its own.">Default cost/profit center</FieldLabel>
                                    <CostCenterPicker tenantId={formTenantId} value={form.data.cost_center_id} onChange={(id) => form.setData('cost_center_id', id)} error={form.errors.cost_center_id} />
                                </div>
                            </div>

                            {form.data.booking_types.length > 0 && (
                                <div>
                                    <div className="mb-2" style={{ fontSize: '13px', fontWeight: 600, color: 'var(--af-navy)' }}>
                                        Per-type overrides{' '}
                                        <InfoTip>Optional. Send a booking type’s income to a different account or center than this item’s defaults.</InfoTip>
                                    </div>
                                    <div className="d-flex flex-column gap-2">
                                        {form.data.booking_types.map((row, index) => (
                                            <div key={index} className="row g-2 align-items-start">
                                                <div className="col-12 col-md-3 pt-md-2" style={{ fontSize: '13px', fontWeight: 500 }}>
                                                    {typeOptions.find((option) => option.id === row.booking_type_id)?.name ?? <span style={{ color: 'var(--af-label)' }}>Select a type above</span>}
                                                </div>
                                                <div className="col-12 col-md-5">
                                                    <AccountPicker
                                                        tenantId={formTenantId}
                                                        value={row.account_id}
                                                        onChange={(id) => updateType(index, { account_id: id })}
                                                        filterType="revenue"
                                                        placeholder="Revenue: use item default"
                                                        error={(form.errors as Record<string, string>)[`booking_types.${index}.account_id`]}
                                                    />
                                                </div>
                                                <div className="col-12 col-md-4">
                                                    <CostCenterPicker
                                                        tenantId={formTenantId}
                                                        value={row.cost_center_id}
                                                        onChange={(id) => updateType(index, { cost_center_id: id })}
                                                        emptyLabel="Center: use item default"
                                                        error={(form.errors as Record<string, string>)[`booking_types.${index}.cost_center_id`]}
                                                    />
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </FormSection>
                    ) : (
                        <div style={{ fontSize: '12.5px', color: 'var(--af-label)' }}>
                            Revenue accounts and cost centers are set by finance, an Admin, a Super Admin, a Platform Admin or a Portal Manager.
                        </div>
                    )}
                </form>
            </Modal>
        </>
    );
}

function FieldLabel({ children, tip }: { children: string; tip: string }) {
    return (
        <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
            {children} <InfoTip>{tip}</InfoTip>
        </label>
    );
}
