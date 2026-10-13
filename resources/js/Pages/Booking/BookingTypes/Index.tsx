import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Tags, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { useConfirm } from '@/Components/ui/ConfirmProvider';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Table } from '@/Components/ui/Table';
import type { BookingTypeSummary } from '@/types/booking';

interface Props {
    types: BookingTypeSummary[];
    viewingAllTenants: boolean;
    canManage: boolean;
}

export default function BookingTypesIndex({ types, viewingAllTenants, canManage }: Props) {
    const canWrite = canManage && !viewingAllTenants;
    const confirm = useConfirm();
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<BookingTypeSummary | null>(null);

    const form = useForm({ name: '' });

    function openCreate() {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setModalOpen(true);
    }

    function openEdit(type: BookingTypeSummary) {
        setEditing(type);
        form.setData('name', type.name);
        form.clearErrors();
        setModalOpen(true);
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        if (editing) {
            form.put(route('booking-types.update', editing.id), { onSuccess: () => setModalOpen(false) });
        } else {
            form.post(route('booking-types.store'), {
                onSuccess: () => {
                    setModalOpen(false);
                    form.reset();
                },
            });
        }
    }

    async function destroy(type: BookingTypeSummary) {
        if (await confirm(`Delete "${type.name}"?`, { variant: 'danger', confirmLabel: 'Delete' })) {
            router.delete(route('booking-types.destroy', type.id));
        }
    }

    return (
        <>
            <Head title="Booking Types" />

            <PageHeader
                title="Booking Types"
                subtitle="Reusable kinds of booking (Wedding Pray, Holy Mass, Meeting …). Attach them to a bookable item and set the price there."
                action={
                    canWrite && (
                        <Button leadingIcon={<Plus size={16} />} onClick={openCreate}>
                            New Type
                        </Button>
                    )
                }
            />

            <Card padded={false}>
                {types.length === 0 ? (
                    <EmptyState
                        icon={<Tags size={20} />}
                        title="No booking types yet"
                        description="Create the kinds of booking you offer, then add them to your bookable items with a price."
                        action={canWrite && <Button onClick={openCreate}>New Type</Button>}
                    />
                ) : (
                    <Table cards>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Name</Table.HeadCell>
                            <Table.HeadCell>Used on</Table.HeadCell>
                            {viewingAllTenants && <Table.HeadCell>Tenant</Table.HeadCell>}
                            {canWrite && <Table.HeadCell className="text-end pe-3">Actions</Table.HeadCell>}
                        </Table.Head>
                        <tbody>
                            {types.map((type) => (
                                <Table.Row key={type.id}>
                                    <Table.Cell className="ps-3" label="Name">
                                        {type.name}
                                    </Table.Cell>
                                    <Table.Cell label="Used on">
                                        {type.bookable_items_count} {type.bookable_items_count === 1 ? 'item' : 'items'}
                                    </Table.Cell>
                                    {viewingAllTenants && <Table.Cell label="Tenant">{type.tenant_name}</Table.Cell>}
                                    {canWrite && (
                                        <Table.Cell className="text-end pe-3" label="Actions">
                                            <div className="d-flex justify-content-end gap-1">
                                                <button type="button" className="btn btn-sm p-1" style={{ color: 'var(--af-label)' }} onClick={() => openEdit(type)} aria-label="Edit">
                                                    <Pencil size={15} />
                                                </button>
                                                <button type="button" className="btn btn-sm p-1" style={{ color: 'var(--af-danger)' }} onClick={() => destroy(type)} aria-label="Delete">
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
                title={editing ? 'Edit Type' : 'New Type'}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={submit} loading={form.processing}>
                            {editing ? 'Save changes' : 'Create type'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit}>
                    <Input label="Name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
                </form>
            </Modal>
        </>
    );
}
