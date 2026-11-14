import { Head, router, useForm } from '@inertiajs/react';
import { Building2, HeartHandshake, LogIn, Pencil, Plus, type LucideIcon } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { InfoTip } from '@/Components/ui/InfoTip';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Table } from '@/Components/ui/Table';
import type { Paginated, Tenant } from '@/types/finance';

interface ModuleOption {
    value: string;
    label: string;
    description: string;
}

interface Props {
    tenants: Paginated<Tenant>;
    moduleOptions: ModuleOption[];
}

const MODULE_ICONS: Record<string, LucideIcon> = {
    donations: HeartHandshake,
};

/**
 * One toggle chip per optional module, saved the moment it is clicked — the
 * quick way to switch a module on or off for a service without opening it.
 */
function ModuleChips({ tenant, moduleOptions }: { tenant: Tenant; moduleOptions: ModuleOption[] }) {
    const [enabled, setEnabled] = useState<string[]>(tenant.modules ?? []);
    const [saving, setSaving] = useState(false);

    function toggle(value: string) {
        const previous = enabled;
        const next = enabled.includes(value) ? enabled.filter((module) => module !== value) : [...enabled, value];

        setEnabled(next);
        setSaving(true);
        router.put(
            route('platform.tenants.modules', tenant.id),
            { modules: next },
            {
                preserveScroll: true,
                onError: () => setEnabled(previous),
                onFinish: () => setSaving(false),
            },
        );
    }

    return (
        <div className="d-flex flex-wrap gap-1">
            {moduleOptions.map((module) => {
                const Icon = MODULE_ICONS[module.value] ?? Building2;
                const on = enabled.includes(module.value);

                return (
                    <button
                        key={module.value}
                        type="button"
                        disabled={saving}
                        aria-pressed={on}
                        title={`${on ? 'Switch off' : 'Switch on'} ${module.label} — ${module.description}`}
                        onClick={() => toggle(module.value)}
                        className="d-inline-flex align-items-center gap-1"
                        style={{
                            fontSize: '12px',
                            fontWeight: 600,
                            padding: '3px 10px',
                            borderRadius: '999px',
                            border: `1px solid ${on ? 'var(--af-primary)' : 'var(--af-border)'}`,
                            backgroundColor: on ? 'var(--af-primary)' : 'var(--af-surface)',
                            color: on ? '#fff' : 'var(--af-label)',
                            opacity: saving ? 0.6 : 1,
                        }}
                    >
                        <Icon size={13} />
                        {module.label}
                    </button>
                );
            })}
        </div>
    );
}
export default function TenantsIndex({ tenants, moduleOptions }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Tenant | null>(null);

    const form = useForm<{
        name: string;
        slug: string;
        base_currency: string;
        is_active: boolean;
        modules: string[];
    }>({
        name: '',
        slug: '',
        base_currency: 'EGP',
        is_active: true,
        modules: [],
    });

    function openCreate() {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setModalOpen(true);
    }

    function openEdit(tenant: Tenant) {
        setEditing(tenant);
        form.setData({
            name: tenant.name,
            slug: tenant.slug,
            base_currency: tenant.base_currency,
            is_active: tenant.is_active,
            modules: tenant.modules ?? [],
        });
        form.clearErrors();
        setModalOpen(true);
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        if (editing) {
            form.put(route('platform.tenants.update', editing.id), { onSuccess: () => setModalOpen(false) });
        } else {
            form.post(route('platform.tenants.store'), {
                onSuccess: () => {
                    setModalOpen(false);
                    form.reset();
                },
            });
        }
    }

    function switchInto(tenant: Tenant) {
        router.post(route('platform.switch-tenant', tenant.id));
    }

    return (
        <>
            <Head title="Services" />

            <PageHeader
                title="Services"
                subtitle="Create and configure services. Every new service starts with the standard chart of accounts."
                action={
                    <Button leadingIcon={<Plus size={16} />} onClick={openCreate}>
                        New Service
                    </Button>
                }
            />

            <Card padded={false}>
                {tenants.data.length === 0 ? (
                    <EmptyState
                        icon={<Building2 size={20} />}
                        title="No services yet"
                        description="Create the first service to get started."
                        action={<Button onClick={openCreate}>New Service</Button>}
                    />
                ) : (
                    <Table cards>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Name</Table.HeadCell>
                            <Table.HeadCell>Slug</Table.HeadCell>
                            <Table.HeadCell>Base Currency</Table.HeadCell>
                            {moduleOptions.length > 0 && <Table.HeadCell>Modules</Table.HeadCell>}
                            <Table.HeadCell>Status</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Actions</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {tenants.data.map((tenant) => (
                                <Table.Row key={tenant.id}>
                                    <Table.Cell className="ps-3" label="Name">
                                        {tenant.name}
                                    </Table.Cell>
                                    <Table.Cell label="Slug">{tenant.slug}</Table.Cell>
                                    <Table.Cell label="Base Currency">{tenant.base_currency}</Table.Cell>
                                    {moduleOptions.length > 0 && (
                                        <Table.Cell label="Modules">
                                            <ModuleChips key={`${tenant.id}-${(tenant.modules ?? []).join(",")}`} tenant={tenant} moduleOptions={moduleOptions} />
                                        </Table.Cell>
                                    )}
                                    <Table.Cell label="Status">
                                        <Badge variant={tenant.is_active ? 'success' : 'neutral'}>{tenant.is_active ? 'Active' : 'Inactive'}</Badge>
                                    </Table.Cell>
                                    <Table.Cell className="text-end pe-3" label="Actions">
                                        <div className="d-flex justify-content-end gap-1">
                                            <Button variant="outline" size="sm" leadingIcon={<LogIn size={14} />} onClick={() => switchInto(tenant)}>
                                                Switch into
                                            </Button>
                                            <button
                                                type="button"
                                                className="btn btn-sm p-1"
                                                style={{ color: 'var(--af-label)' }}
                                                onClick={() => openEdit(tenant)}
                                                aria-label="Edit"
                                            >
                                                <Pencil size={15} />
                                            </button>
                                        </div>
                                    </Table.Cell>
                                </Table.Row>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>

            <Modal
                open={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editing ? 'Edit Service' : 'New Service'}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={submit} loading={form.processing}>
                            {editing ? 'Save changes' : 'Create service'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="d-flex flex-column gap-3">
                    <Input label="Name" help="The business or organisation name this service represents." value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
                    <Input label="Slug" help="A short unique identifier using only letters, numbers, dashes or underscores, for example my-company." value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} error={form.errors.slug} />
                    <Input
                        label="Base Currency"
                        help="The main currency this service reports in. Foreign-currency amounts are converted to it."
                        value={form.data.base_currency}
                        onChange={(e) => form.setData('base_currency', e.target.value.toUpperCase())}
                        error={form.errors.base_currency}
                        maxLength={3}
                    />
                    {moduleOptions.length > 0 && (
                        <div>
                            <div style={{ fontSize: '12px', fontWeight: 600, color: '#5e6b80', marginBottom: '6px' }}>
                                Modules
                                <span className="ms-1">
                                    <InfoTip label="About Modules">Extra features this service can use. A module that is off is hidden from its menu and cannot be opened.</InfoTip>
                                </span>
                            </div>
                            <div className="d-flex flex-column gap-2">
                                {moduleOptions.map((module) => (
                                    <label key={module.value} className="d-flex align-items-start gap-2" style={{ fontSize: '14px' }}>
                                        <input
                                            type="checkbox"
                                            className="mt-1"
                                            checked={form.data.modules.includes(module.value)}
                                            onChange={(e) =>
                                                form.setData(
                                                    'modules',
                                                    e.target.checked ? [...form.data.modules, module.value] : form.data.modules.filter((value) => value !== module.value),
                                                )
                                            }
                                        />
                                        <span>
                                            {module.label}
                                            <span className="d-block" style={{ fontSize: '12px', color: 'var(--af-label)' }}>{module.description}</span>
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </div>
                    )}
                    {editing && (
                        <label className="d-flex align-items-center gap-2" style={{ fontSize: '14px' }}>
                            <input
                                type="checkbox"
                                checked={form.data.is_active}
                                onChange={(e) => form.setData('is_active', e.target.checked)}
                            />
                            Active
                        </label>
                    )}
                </form>
            </Modal>
        </>
    );
}
