import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Truck } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { useConfirm } from '@/Components/ui/ConfirmProvider';
import { EmptyState } from '@/Components/ui/EmptyState';
import { ExportButton } from '@/Components/ui/ExportButton';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Table } from '@/Components/ui/Table';
import { AppLayout } from '@/Layouts/AppLayout';
import type { Paginated, Vendor } from '@/types/finance';

interface Props {
    vendors: Paginated<Vendor>;
    filters: { search?: string };
}

export default function VendorsIndex({ vendors, filters }: Props) {
    const confirm = useConfirm();
    const [search, setSearch] = useState(filters.search ?? '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Vendor | null>(null);

    const form = useForm({
        name: '',
        email: '',
        tax_number: '',
        payment_terms: '',
    });

    function openCreate() {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setModalOpen(true);
    }

    function openEdit(vendor: Vendor) {
        setEditing(vendor);
        form.setData({
            name: vendor.name,
            email: vendor.email ?? '',
            tax_number: vendor.tax_number ?? '',
            payment_terms: vendor.payment_terms ?? '',
        });
        form.clearErrors();
        setModalOpen(true);
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        if (editing) {
            form.put(route('vendors.update', editing.id), { onSuccess: () => setModalOpen(false) });
        } else {
            form.post(route('vendors.store'), {
                onSuccess: () => {
                    setModalOpen(false);
                    form.reset();
                },
            });
        }
    }

    async function destroy(vendor: Vendor) {
        if (await confirm(`Delete vendor "${vendor.name}"?`, { variant: 'danger', confirmLabel: 'Delete' })) {
            router.delete(route('vendors.destroy', vendor.id));
        }
    }

    function runSearch(e: FormEvent) {
        e.preventDefault();
        router.get(route('vendors.index'), { search }, { preserveState: true });
    }

    return (
        <AppLayout>
            <Head title="Vendors" />

            <PageHeader
                title="Vendors"
                subtitle="Everyone you record expenses against."
                action={
                    <div className="d-flex gap-2">
                        <ExportButton href={route('vendors.export', filters)} />
                        <Button leadingIcon={<Plus size={16} />} onClick={openCreate}>
                            New Vendor
                        </Button>
                    </div>
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <form onSubmit={runSearch} className="d-flex gap-2" style={{ maxWidth: '320px' }}>
                        <Input placeholder="Search by name or email…" value={search} onChange={(e) => setSearch(e.target.value)} />
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                    </form>
                </div>

                {vendors.data.length === 0 ? (
                    <EmptyState
                        icon={<Truck size={20} />}
                        title="No vendors yet"
                        description="Add a vendor to tag against expenses."
                        action={<Button onClick={openCreate}>New Vendor</Button>}
                    />
                ) : (
                    <Table>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Name</Table.HeadCell>
                            <Table.HeadCell>Email</Table.HeadCell>
                            <Table.HeadCell>Payment terms</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Actions</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {vendors.data.map((vendor) => (
                                <Table.Row key={vendor.id}>
                                    <Table.Cell className="ps-3">{vendor.name}</Table.Cell>
                                    <Table.Cell>{vendor.email ?? '—'}</Table.Cell>
                                    <Table.Cell>{vendor.payment_terms ?? '—'}</Table.Cell>
                                    <Table.Cell className="text-end pe-3">
                                        <div className="d-flex justify-content-end gap-1">
                                            <button type="button" className="btn btn-sm p-1" style={{ color: 'var(--af-label)' }} onClick={() => openEdit(vendor)} aria-label="Edit">
                                                <Pencil size={15} />
                                            </button>
                                            <button type="button" className="btn btn-sm p-1" style={{ color: 'var(--af-danger)' }} onClick={() => destroy(vendor)} aria-label="Delete">
                                                <Trash2 size={15} />
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
                title={editing ? 'Edit Vendor' : 'New Vendor'}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={submit} loading={form.processing}>
                            {editing ? 'Save changes' : 'Create vendor'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="d-flex flex-column gap-3">
                    <Input label="Name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
                    <Input label="Email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} error={form.errors.email} />
                    <Input label="Tax number" value={form.data.tax_number} onChange={(e) => form.setData('tax_number', e.target.value)} error={form.errors.tax_number} />
                    <Input label="Payment terms" value={form.data.payment_terms} onChange={(e) => form.setData('payment_terms', e.target.value)} error={form.errors.payment_terms} placeholder="e.g. Net 30" />
                </form>
            </Modal>
        </AppLayout>
    );
}
