import { Head, Link, router, useForm } from '@inertiajs/react';
import { HeartHandshake, Pencil, Plus } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { FilterPanel } from '@/Components/ui/FilterPanel';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Table } from '@/Components/ui/Table';
import type { Donor, DonorsPage } from '@/types/donations';

interface Props {
    donors: DonorsPage;
    filters: { search: string | null };
    canCreate: boolean;
    canManage: boolean;
}

export default function DonorsIndex({ donors, filters, canCreate, canManage }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Donor | null>(null);

    const form = useForm({ name: '', phone: '', email: '', notes: '' });

    function openCreate() {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setModalOpen(true);
    }

    function openEdit(donor: Donor) {
        setEditing(donor);
        form.setData({ name: donor.name, phone: donor.phone ?? '', email: donor.email ?? '', notes: donor.notes ?? '' });
        form.clearErrors();
        setModalOpen(true);
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        if (editing) {
            form.put(route('donors.update', editing.id), { preserveScroll: true, onSuccess: () => setModalOpen(false) });
        } else {
            form.post(route('donors.store'), {
                preserveScroll: true,
                onSuccess: () => {
                    setModalOpen(false);
                    form.reset();
                },
            });
        }
    }

    function runSearch(e: FormEvent) {
        e.preventDefault();
        router.get(route('donors.index'), { search }, { preserveState: true });
    }

    return (
        <>
            <Head title="Donors" />

            <PageHeader
                title="Donors"
                subtitle="The people who give. A donor’s phone and email are optional."
                action={
                    canCreate && (
                        <Button leadingIcon={<Plus size={16} />} onClick={openCreate}>
                            New Donor
                        </Button>
                    )
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <FilterPanel active={Boolean(filters.search)}>
                        <form onSubmit={runSearch} className="af-filter-bar">
                            <Input placeholder="Search by name, phone or email…" value={search} onChange={(e) => setSearch(e.target.value)} style={{ maxWidth: '280px' }} />
                            <Button type="submit" variant="outline">
                                Search
                            </Button>
                        </form>
                    </FilterPanel>
                </div>

                {donors.data.length === 0 ? (
                    <EmptyState
                        icon={<HeartHandshake size={20} />}
                        title="No donors yet"
                        description="Add the people who donate, or record donations without a donor."
                        action={canCreate && <Button onClick={openCreate}>New Donor</Button>}
                    />
                ) : (
                    <Table cards>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Name</Table.HeadCell>
                            <Table.HeadCell>Phone</Table.HeadCell>
                            <Table.HeadCell>Email</Table.HeadCell>
                            <Table.HeadCell>Donations</Table.HeadCell>
                            {canManage && <Table.HeadCell className="text-end pe-3">Actions</Table.HeadCell>}
                        </Table.Head>
                        <tbody>
                            {donors.data.map((donor) => (
                                <Table.Row key={donor.id}>
                                    <Table.Cell className="ps-3" label="Name">
                                        {donor.name}
                                    </Table.Cell>
                                    <Table.Cell label="Phone">{donor.phone ?? '—'}</Table.Cell>
                                    <Table.Cell label="Email">{donor.email ?? '—'}</Table.Cell>
                                    <Table.Cell label="Donations">{donor.donations_count}</Table.Cell>
                                    {canManage && (
                                        <Table.Cell className="text-end pe-3" label="Actions">
                                            <button type="button" className="btn btn-sm p-1" style={{ color: 'var(--af-label)' }} onClick={() => openEdit(donor)} aria-label="Edit">
                                                <Pencil size={15} />
                                            </button>
                                        </Table.Cell>
                                    )}
                                </Table.Row>
                            ))}
                        </tbody>
                    </Table>
                )}

                <div className="pagination">
                    <span>
                        {donors.total} donor{donors.total === 1 ? '' : 's'}
                    </span>
                    <div>
                        {donors.links.map((link, index) => (
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
                title={editing ? 'Edit Donor' : 'New Donor'}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={submit} loading={form.processing}>
                            {editing ? 'Save changes' : 'Add donor'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="d-flex flex-column gap-3">
                    <Input label="Name" help="The donor’s full name as it should appear on their receipt." value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
                    <Input label="Phone (optional)" help="A number to reach the donor on. You can leave it blank." value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} error={form.errors.phone} />
                    <Input label="Email (optional)" help="An email address for the donor. You can leave it blank." type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} error={form.errors.email} />
                    <Input label="Notes (optional)" help="Anything worth remembering about this donor." value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} error={form.errors.notes} />
                </form>
            </Modal>
        </>
    );
}
