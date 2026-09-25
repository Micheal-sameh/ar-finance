import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Users } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { useConfirm } from '@/Components/ui/ConfirmProvider';
import { EmptyState } from '@/Components/ui/EmptyState';
import { ExportButton } from '@/Components/ui/ExportButton';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { Table } from '@/Components/ui/Table';
import type { Client, Paginated } from '@/types/finance';

interface CurrencyOption {
    code: string;
    name: string;
}

interface Props {
    clients: Paginated<Client>;
    filters: { currency?: string; search?: string };
    currencyOptions: CurrencyOption[];
}

export default function ClientsIndex({ clients, filters, currencyOptions }: Props) {
    const confirm = useConfirm();
    const [search, setSearch] = useState(filters.search ?? '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Client | null>(null);

    const form = useForm({
        name: '',
        email: '',
        phone: '',
        tax_number: '',
        address: '',
        currency: 'EGP',
    });

    function openCreate() {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setModalOpen(true);
    }

    function openEdit(client: Client) {
        setEditing(client);
        form.setData({
            name: client.name,
            email: client.email ?? '',
            phone: client.phone ?? '',
            tax_number: client.tax_number ?? '',
            address: client.address ?? '',
            currency: client.currency,
        });
        form.clearErrors();
        setModalOpen(true);
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        if (editing) {
            form.put(route('clients.update', editing.id), { onSuccess: () => setModalOpen(false) });
        } else {
            form.post(route('clients.store'), {
                onSuccess: () => {
                    setModalOpen(false);
                    form.reset();
                },
            });
        }
    }

    async function destroy(client: Client) {
        if (await confirm(`Delete client "${client.name}"?`, { variant: 'danger', confirmLabel: 'Delete' })) {
            router.delete(route('clients.destroy', client.id));
        }
    }

    function runSearch(e: FormEvent) {
        e.preventDefault();
        router.get(route('clients.index'), { ...filters, search }, { preserveState: true });
    }

    function runFilters(next: Partial<Props['filters']>) {
        router.get(route('clients.index'), { ...filters, search, ...next }, { preserveState: true });
    }

    function clearFilters() {
        setSearch('');
        router.get(route('clients.index'), {}, { preserveState: true });
    }

    return (
        <>
            <Head title="Clients" />

            <PageHeader
                title="Clients"
                subtitle="Everyone you invoice."
                action={
                    <div className="d-flex gap-2">
                        <ExportButton href={route('clients.export', filters)} />
                        <Button leadingIcon={<Plus size={16} />} onClick={openCreate}>
                            New Client
                        </Button>
                    </div>
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <form onSubmit={runSearch} className="d-flex gap-2 flex-wrap align-items-end">
                        <Input
                            placeholder="Search by name or email…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            style={{ maxWidth: '240px' }}
                        />
                        <Select
                            value={filters.currency ?? ''}
                            onChange={(e) => runFilters({ currency: e.target.value || undefined })}
                            style={{ maxWidth: '170px' }}
                        >
                            <option value="">All currencies</option>
                            {currencyOptions.map((currency) => (
                                <option key={currency.code} value={currency.code}>
                                    {currency.code}
                                </option>
                            ))}
                        </Select>
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                        {(filters.currency || filters.search) && (
                            <Button type="button" variant="ghost" onClick={clearFilters}>
                                Clear
                            </Button>
                        )}
                    </form>
                </div>

                {clients.data.length === 0 ? (
                    <EmptyState
                        icon={<Users size={20} />}
                        title="No clients yet"
                        description="Add a client before creating your first invoice."
                        action={<Button onClick={openCreate}>New Client</Button>}
                    />
                ) : (
                    <Table cards>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Name</Table.HeadCell>
                            <Table.HeadCell>Email</Table.HeadCell>
                            <Table.HeadCell>Phone</Table.HeadCell>
                            <Table.HeadCell>Currency</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Actions</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {clients.data.map((client) => (
                                <Table.Row
                                    key={client.id}
                                    style={{ cursor: 'pointer' }}
                                    onClick={() => router.get(route('clients.show', client.id))}
                                >
                                    <Table.Cell className="ps-3" label="Name">{client.name}</Table.Cell>
                                    <Table.Cell label="Email">{client.email ?? '—'}</Table.Cell>
                                    <Table.Cell label="Phone">{client.phone ?? '—'}</Table.Cell>
                                    <Table.Cell label="Currency">{client.currency}</Table.Cell>
                                    <Table.Cell className="text-end pe-3" label="Actions">
                                        <div className="d-flex justify-content-end gap-1">
                                            <button
                                                type="button"
                                                className="btn btn-sm p-1"
                                                style={{ color: 'var(--af-label)' }}
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    openEdit(client);
                                                }}
                                                aria-label="Edit"
                                            >
                                                <Pencil size={15} />
                                            </button>
                                            <button
                                                type="button"
                                                className="btn btn-sm p-1"
                                                style={{ color: 'var(--af-danger)' }}
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    destroy(client);
                                                }}
                                                aria-label="Delete"
                                            >
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
                title={editing ? 'Edit Client' : 'New Client'}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={submit} loading={form.processing}>
                            {editing ? 'Save changes' : 'Create client'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="d-flex flex-column gap-3">
                    <Input label="Name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
                    <Input label="Email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} error={form.errors.email} />
                    <Input label="Phone" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} error={form.errors.phone} />
                    <Input label="Tax number" value={form.data.tax_number} onChange={(e) => form.setData('tax_number', e.target.value)} error={form.errors.tax_number} />
                    <Input label="Address" value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} error={form.errors.address} />
                    <Select label="Currency" value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value)} error={form.errors.currency}>
                        {currencyOptions.map((currency) => (
                            <option key={currency.code} value={currency.code}>
                                {currency.code} — {currency.name}
                            </option>
                        ))}
                    </Select>
                </form>
            </Modal>
        </>
    );
}
