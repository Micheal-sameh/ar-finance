import { Head, router, useForm } from '@inertiajs/react';
import { Landmark, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { Table } from '@/Components/ui/Table';
import { AppLayout } from '@/Layouts/AppLayout';
import type { BankAccount, Paginated } from '@/types/finance';

interface CurrencyOption {
    code: string;
    name: string;
}

interface Props {
    bankAccounts: Paginated<BankAccount>;
    filters: { search?: string };
    currencyOptions: CurrencyOption[];
}

export default function BankAccountsIndex({ bankAccounts, filters, currencyOptions }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<BankAccount | null>(null);

    const form = useForm<{
        name: string;
        account_id: number | null;
        bank_name: string;
        account_number: string;
        currency: string;
    }>({
        name: '',
        account_id: null,
        bank_name: '',
        account_number: '',
        currency: 'USD',
    });

    function openCreate() {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setModalOpen(true);
    }

    function openEdit(bankAccount: BankAccount) {
        setEditing(bankAccount);
        form.setData({
            name: bankAccount.name,
            account_id: bankAccount.account_id,
            bank_name: bankAccount.bank_name ?? '',
            account_number: bankAccount.account_number ?? '',
            currency: bankAccount.currency,
        });
        form.clearErrors();
        setModalOpen(true);
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        if (editing) {
            form.put(route('bank-accounts.update', editing.id), { onSuccess: () => setModalOpen(false) });
        } else {
            form.post(route('bank-accounts.store'), { onSuccess: () => setModalOpen(false) });
        }
    }

    function destroy(bankAccount: BankAccount) {
        if (confirm(`Delete bank account "${bankAccount.name}"?`)) {
            router.delete(route('bank-accounts.destroy', bankAccount.id));
        }
    }

    function runSearch(e: FormEvent) {
        e.preventDefault();
        router.get(route('bank-accounts.index'), { search }, { preserveState: true });
    }

    return (
        <AppLayout>
            <Head title="Bank Accounts" />

            <PageHeader
                title="Bank Accounts"
                subtitle="Each links to a Chart of Accounts cash/bank account for reconciliation."
                action={
                    <Button leadingIcon={<Plus size={16} />} onClick={openCreate}>
                        New Bank Account
                    </Button>
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <form onSubmit={runSearch} className="d-flex gap-2" style={{ maxWidth: '320px' }}>
                        <Input placeholder="Search by name…" value={search} onChange={(e) => setSearch(e.target.value)} />
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                    </form>
                </div>

                {bankAccounts.data.length === 0 ? (
                    <EmptyState
                        icon={<Landmark size={20} />}
                        title="No bank accounts yet"
                        description="Add a bank account to start importing and reconciling transactions."
                        action={<Button onClick={openCreate}>New Bank Account</Button>}
                    />
                ) : (
                    <Table>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Name</Table.HeadCell>
                            <Table.HeadCell>GL Account</Table.HeadCell>
                            <Table.HeadCell>Bank</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Actions</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {bankAccounts.data.map((bankAccount) => (
                                <Table.Row key={bankAccount.id} style={{ cursor: 'pointer' }} onClick={() => router.get(route('bank-accounts.show', bankAccount.id))}>
                                    <Table.Cell className="ps-3">{bankAccount.name}</Table.Cell>
                                    <Table.Cell>{bankAccount.account ? `${bankAccount.account.code} · ${bankAccount.account.name}` : '—'}</Table.Cell>
                                    <Table.Cell>{bankAccount.bank_name ?? '—'}</Table.Cell>
                                    <Table.Cell className="text-end pe-3" onClick={(e) => e.stopPropagation()}>
                                        <div className="d-flex justify-content-end gap-1">
                                            <button type="button" className="btn btn-sm p-1" style={{ color: 'var(--af-label)' }} onClick={() => openEdit(bankAccount)} aria-label="Edit">
                                                <Pencil size={15} />
                                            </button>
                                            <button type="button" className="btn btn-sm p-1" style={{ color: 'var(--af-danger)' }} onClick={() => destroy(bankAccount)} aria-label="Delete">
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
                title={editing ? 'Edit Bank Account' : 'New Bank Account'}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={submit} loading={form.processing}>
                            {editing ? 'Save changes' : 'Add bank account'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="d-flex flex-column gap-3">
                    <Input label="Name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} placeholder="e.g. Main Checking" />
                    <div>
                        <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                            GL account
                        </label>
                        <AccountPicker value={form.data.account_id} onChange={(id) => form.setData('account_id', id)} error={form.errors.account_id} placeholder="e.g. Bank" />
                    </div>
                    <Input label="Bank name" value={form.data.bank_name} onChange={(e) => form.setData('bank_name', e.target.value)} error={form.errors.bank_name} />
                    <Input label="Account number" value={form.data.account_number} onChange={(e) => form.setData('account_number', e.target.value)} error={form.errors.account_number} />
                    <Select label="Currency" value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value)} error={form.errors.currency}>
                        {currencyOptions.map((currency) => (
                            <option key={currency.code} value={currency.code}>
                                {currency.code} — {currency.name}
                            </option>
                        ))}
                    </Select>
                </form>
            </Modal>
        </AppLayout>
    );
}
