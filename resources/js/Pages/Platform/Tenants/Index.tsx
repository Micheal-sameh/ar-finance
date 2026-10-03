import { Head, router, useForm } from '@inertiajs/react';
import { Building2, LogIn, Pencil, Plus } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Table } from '@/Components/ui/Table';
import type { Paginated, Tenant } from '@/types/finance';

interface Props {
    tenants: Paginated<Tenant>;
}

export default function TenantsIndex({ tenants }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Tenant | null>(null);

    const form = useForm<{
        name: string;
        slug: string;
        base_currency: string;
        is_active: boolean;
    }>({
        name: '',
        slug: '',
        base_currency: 'EGP',
        is_active: true,
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
            <Head title="Tenants" />

            <PageHeader
                title="Tenants"
                subtitle="Create and configure tenants. Every new tenant starts with the standard chart of accounts."
                action={
                    <Button leadingIcon={<Plus size={16} />} onClick={openCreate}>
                        New Tenant
                    </Button>
                }
            />

            <Card padded={false}>
                {tenants.data.length === 0 ? (
                    <EmptyState
                        icon={<Building2 size={20} />}
                        title="No tenants yet"
                        description="Create the first tenant to get started."
                        action={<Button onClick={openCreate}>New Tenant</Button>}
                    />
                ) : (
                    <Table cards>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Name</Table.HeadCell>
                            <Table.HeadCell>Slug</Table.HeadCell>
                            <Table.HeadCell>Base Currency</Table.HeadCell>
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
                title={editing ? 'Edit Tenant' : 'New Tenant'}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={submit} loading={form.processing}>
                            {editing ? 'Save changes' : 'Create tenant'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="d-flex flex-column gap-3">
                    <Input label="Name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
                    <Input label="Slug" value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} error={form.errors.slug} />
                    <Input
                        label="Base Currency"
                        value={form.data.base_currency}
                        onChange={(e) => form.setData('base_currency', e.target.value.toUpperCase())}
                        error={form.errors.base_currency}
                        maxLength={3}
                    />
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
