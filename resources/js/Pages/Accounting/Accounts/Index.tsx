import { Head, router, useForm } from '@inertiajs/react';
import { ListTree, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { Table } from '@/Components/ui/Table';
import { AppLayout } from '@/Layouts/AppLayout';
import type { Account, AccountType, Paginated } from '@/types/finance';

interface Props {
    accounts: Paginated<Account>;
    filters: { type?: string; is_active?: string; search?: string };
}

const ACCOUNT_TYPES: { value: AccountType; label: string }[] = [
    { value: 'asset', label: 'Asset' },
    { value: 'liability', label: 'Liability' },
    { value: 'equity', label: 'Equity' },
    { value: 'revenue', label: 'Revenue' },
    { value: 'expense', label: 'Expense' },
];

function typeBadgeVariant(type: AccountType) {
    return { asset: 'primary', liability: 'warning', equity: 'info', revenue: 'success', expense: 'danger' }[type] as
        | 'primary'
        | 'warning'
        | 'info'
        | 'success'
        | 'danger';
}

export default function AccountsIndex({ accounts, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Account | null>(null);

    const form = useForm({
        code: '',
        name: '',
        type: 'asset' as AccountType,
        parent_id: null as number | null,
    });

    function openCreate() {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setModalOpen(true);
    }

    function openEdit(account: Account) {
        setEditing(account);
        form.setData({
            code: account.code,
            name: account.name,
            type: account.type,
            parent_id: account.parent_id,
        });
        form.clearErrors();
        setModalOpen(true);
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        if (editing) {
            form.put(route('accounts.update', editing.id), { onSuccess: () => setModalOpen(false) });
        } else {
            form.post(route('accounts.store'), { onSuccess: () => setModalOpen(false) });
        }
    }

    function destroy(account: Account) {
        if (confirm(`Delete account ${account.code} — ${account.name}?`)) {
            router.delete(route('accounts.destroy', account.id));
        }
    }

    function runSearch(e: FormEvent) {
        e.preventDefault();
        router.get(route('accounts.index'), { ...filters, search }, { preserveState: true });
    }

    return (
        <AppLayout>
            <Head title="Chart of Accounts" />

            <PageHeader
                title="Chart of Accounts"
                subtitle="Every posting account in the ledger, grouped by type."
                action={
                    <Button leadingIcon={<Plus size={16} />} onClick={openCreate}>
                        New Account
                    </Button>
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <form onSubmit={runSearch} className="d-flex gap-2" style={{ maxWidth: '320px' }}>
                        <Input
                            placeholder="Search by code or name…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                    </form>
                </div>

                {accounts.data.length === 0 ? (
                    <EmptyState
                        icon={<ListTree size={20} />}
                        title="No accounts yet"
                        description="Create your first account to start building the chart of accounts."
                        action={<Button onClick={openCreate}>New Account</Button>}
                    />
                ) : (
                    <Table>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Code</Table.HeadCell>
                            <Table.HeadCell>Name</Table.HeadCell>
                            <Table.HeadCell>Type</Table.HeadCell>
                            <Table.HeadCell>Normal Balance</Table.HeadCell>
                            <Table.HeadCell>Status</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Actions</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {accounts.data.map((account) => (
                                <Table.Row key={account.id}>
                                    <Table.Cell className="ps-3">{account.code}</Table.Cell>
                                    <Table.Cell>
                                        {account.name}
                                        {account.parent && (
                                            <span style={{ color: 'var(--af-label)', fontSize: '12px' }}>
                                                {' '}
                                                &middot; under {account.parent.name}
                                            </span>
                                        )}
                                    </Table.Cell>
                                    <Table.Cell>
                                        <Badge variant={typeBadgeVariant(account.type)}>{account.type}</Badge>
                                    </Table.Cell>
                                    <Table.Cell style={{ textTransform: 'capitalize' }}>{account.normal_balance}</Table.Cell>
                                    <Table.Cell>
                                        <Badge variant={account.is_active ? 'success' : 'neutral'}>
                                            {account.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </Table.Cell>
                                    <Table.Cell className="text-end pe-3">
                                        <div className="d-flex justify-content-end gap-1">
                                            <button
                                                type="button"
                                                className="btn btn-sm p-1"
                                                style={{ color: 'var(--af-label)' }}
                                                onClick={() => openEdit(account)}
                                                aria-label="Edit"
                                            >
                                                <Pencil size={15} />
                                            </button>
                                            <button
                                                type="button"
                                                className="btn btn-sm p-1"
                                                style={{ color: 'var(--af-danger)' }}
                                                onClick={() => destroy(account)}
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
                title={editing ? 'Edit Account' : 'New Account'}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={submit} loading={form.processing}>
                            {editing ? 'Save changes' : 'Create account'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="d-flex flex-column gap-3">
                    <Input
                        label="Code"
                        value={form.data.code}
                        onChange={(e) => form.setData('code', e.target.value)}
                        error={form.errors.code}
                        placeholder="e.g. 1000"
                    />
                    <Input
                        label="Name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        error={form.errors.name}
                        placeholder="e.g. Cash"
                    />
                    <Select
                        label="Type"
                        value={form.data.type}
                        onChange={(e) => form.setData('type', e.target.value as AccountType)}
                        error={form.errors.type}
                    >
                        {ACCOUNT_TYPES.map((t) => (
                            <option key={t.value} value={t.value}>
                                {t.label}
                            </option>
                        ))}
                    </Select>
                    <div>
                        <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                            Parent account (optional)
                        </label>
                        <AccountPicker
                            value={form.data.parent_id}
                            onChange={(id) => form.setData('parent_id', id)}
                            error={form.errors.parent_id}
                            placeholder="No parent"
                        />
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}
