import { Head, router, useForm } from '@inertiajs/react';
import { Check, Link2, Link2Off, Plus } from 'lucide-react';
import { FormEvent, Fragment, useState } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Select';
import { Table } from '@/Components/ui/Table';
import { AppLayout } from '@/Layouts/AppLayout';
import type { BankAccount, BankTransaction, UnmatchedJournalLine } from '@/types/finance';

interface Props {
    bankAccount: BankAccount;
    transactions: BankTransaction[];
    unmatchedLines: UnmatchedJournalLine[];
}

function lineAmount(line: { debit: string; credit: string }): number {
    return parseFloat(line.debit) > 0 ? parseFloat(line.debit) : -parseFloat(line.credit);
}

export default function BankAccountsShow({ bankAccount, transactions, unmatchedLines }: Props) {
    const [activeRow, setActiveRow] = useState<number | null>(null);
    const [mode, setMode] = useState<'match' | 'create'>('match');

    const importForm = useForm({ csv: '' });
    const matchForm = useForm({ journal_line_id: '' as number | '' });
    const createForm = useForm({ offset_account_id: null as number | null, description: '' });

    const bankBalance = transactions.reduce((sum, t) => sum + parseFloat(t.amount), 0);
    const matchedCount = transactions.filter((t) => t.matched_journal_line_id !== null).length;

    function submitImport(e: FormEvent) {
        e.preventDefault();
        importForm.post(route('bank-accounts.import', bankAccount.id), {
            onSuccess: () => importForm.reset(),
        });
    }

    function openMatch(transactionId: number) {
        setActiveRow(transactionId);
        setMode('match');
        matchForm.reset();
        matchForm.clearErrors();
    }

    function openCreate(transactionId: number, description: string) {
        setActiveRow(transactionId);
        setMode('create');
        createForm.setData({ offset_account_id: null, description });
        createForm.clearErrors();
    }

    function submitMatch(e: FormEvent, transactionId: number) {
        e.preventDefault();
        matchForm.post(route('bank-transactions.match', transactionId), {
            onSuccess: () => setActiveRow(null),
        });
    }

    function submitCreate(e: FormEvent, transactionId: number) {
        e.preventDefault();
        createForm.post(route('bank-transactions.create-and-match', transactionId), {
            onSuccess: () => setActiveRow(null),
        });
    }

    function unmatch(transactionId: number) {
        if (confirm('Unmatch this transaction?')) {
            router.post(route('bank-transactions.unmatch', transactionId));
        }
    }

    return (
        <AppLayout>
            <Head title={bankAccount.name} />

            <PageHeader
                title={bankAccount.name}
                subtitle={bankAccount.account ? `Reconciling against ${bankAccount.account.code} · ${bankAccount.account.name}` : undefined}
            />

            <div className="row g-3 mb-4">
                <div className="col-md-4">
                    <Card>
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Imported Balance</div>
                        <div style={{ fontSize: '18px', fontWeight: 600 }}>
                            <MoneyDisplay amount={bankBalance} currency={bankAccount.currency} />
                        </div>
                    </Card>
                </div>
                <div className="col-md-4">
                    <Card>
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Transactions</div>
                        <div style={{ fontSize: '18px', fontWeight: 600 }}>{transactions.length}</div>
                    </Card>
                </div>
                <div className="col-md-4">
                    <Card>
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Matched</div>
                        <div style={{ fontSize: '18px', fontWeight: 600 }}>
                            {matchedCount} / {transactions.length}
                        </div>
                    </Card>
                </div>
            </div>

            <Card className="mb-4">
                <div style={{ fontSize: '13px', fontWeight: 600, color: 'var(--af-navy)', marginBottom: '8px' }}>
                    Import Transactions
                </div>
                <form onSubmit={submitImport}>
                    <textarea
                        className="form-control mb-2"
                        style={{ borderRadius: 'var(--af-radius-sm)', fontSize: '13px', fontFamily: 'monospace' }}
                        rows={4}
                        placeholder={'2026-01-05,Customer payment,500\n2026-01-06,Bank fee,-25'}
                        value={importForm.data.csv}
                        onChange={(e) => importForm.setData('csv', e.target.value)}
                    />
                    {importForm.errors.csv && (
                        <div style={{ color: 'var(--af-danger)', fontSize: '12px', marginBottom: '8px' }}>{importForm.errors.csv}</div>
                    )}
                    <div className="d-flex align-items-center justify-content-between">
                        <span style={{ fontSize: '12px', color: 'var(--af-label)' }}>One per line: date,description,amount (negative for withdrawals)</span>
                        <Button type="submit" variant="outline" loading={importForm.processing}>
                            Import
                        </Button>
                    </div>
                </form>
            </Card>

            <Card padded={false}>
                <Table>
                    <Table.Head>
                        <Table.HeadCell className="ps-3">Date</Table.HeadCell>
                        <Table.HeadCell>Description</Table.HeadCell>
                        <Table.HeadCell className="text-end">Amount</Table.HeadCell>
                        <Table.HeadCell>Status</Table.HeadCell>
                        <Table.HeadCell className="text-end pe-3">Actions</Table.HeadCell>
                    </Table.Head>
                    <tbody>
                        {transactions.map((transaction) => (
                            <Fragment key={transaction.id}>
                                <Table.Row>
                                    <Table.Cell className="ps-3">{transaction.date}</Table.Cell>
                                    <Table.Cell>{transaction.description}</Table.Cell>
                                    <Table.Cell className="text-end">
                                        <MoneyDisplay amount={transaction.amount} currency={bankAccount.currency} />
                                    </Table.Cell>
                                    <Table.Cell>
                                        {transaction.matched_journal_line_id ? (
                                            <Badge variant="success">Matched</Badge>
                                        ) : (
                                            <Badge variant="warning">Unmatched</Badge>
                                        )}
                                    </Table.Cell>
                                    <Table.Cell className="text-end pe-3">
                                        {transaction.matched_journal_line_id ? (
                                            <button
                                                type="button"
                                                className="btn btn-sm p-1"
                                                style={{ color: 'var(--af-label)' }}
                                                onClick={() => unmatch(transaction.id)}
                                                aria-label="Unmatch"
                                            >
                                                <Link2Off size={15} />
                                            </button>
                                        ) : (
                                            <div className="d-flex justify-content-end gap-1">
                                                <button
                                                    type="button"
                                                    className="btn btn-sm p-1"
                                                    style={{ color: 'var(--af-primary)' }}
                                                    onClick={() => openMatch(transaction.id)}
                                                    aria-label="Match to existing entry"
                                                >
                                                    <Link2 size={15} />
                                                </button>
                                                <button
                                                    type="button"
                                                    className="btn btn-sm p-1"
                                                    style={{ color: 'var(--af-primary)' }}
                                                    onClick={() => openCreate(transaction.id, transaction.description)}
                                                    aria-label="Create and match"
                                                >
                                                    <Plus size={15} />
                                                </button>
                                            </div>
                                        )}
                                    </Table.Cell>
                                </Table.Row>
                                {activeRow === transaction.id && mode === 'match' && (
                                    <tr>
                                        <td colSpan={5} className="ps-3 pe-3 pb-3">
                                            <form onSubmit={(e) => submitMatch(e, transaction.id)} className="d-flex align-items-end gap-2">
                                                <div style={{ flex: 1 }}>
                                                    <Select
                                                        label="Match to journal line"
                                                        value={matchForm.data.journal_line_id}
                                                        onChange={(e) => matchForm.setData('journal_line_id', e.target.value ? Number(e.target.value) : '')}
                                                        error={matchForm.errors.journal_line_id}
                                                    >
                                                        <option value="">Select a posted journal line…</option>
                                                        {unmatchedLines.map((line) => (
                                                            <option key={line.id} value={line.id}>
                                                                {line.journal_entry?.date} · {line.journal_entry?.description} ·{' '}
                                                                {lineAmount(line) >= 0 ? '+' : ''}
                                                                {lineAmount(line).toFixed(2)}
                                                            </option>
                                                        ))}
                                                    </Select>
                                                </div>
                                                <Button type="submit" size="sm" leadingIcon={<Check size={14} />} loading={matchForm.processing}>
                                                    Match
                                                </Button>
                                                <Button type="button" variant="ghost" size="sm" onClick={() => setActiveRow(null)}>
                                                    Cancel
                                                </Button>
                                            </form>
                                        </td>
                                    </tr>
                                )}
                                {activeRow === transaction.id && mode === 'create' && (
                                    <tr>
                                        <td colSpan={5} className="ps-3 pe-3 pb-3">
                                            <form onSubmit={(e) => submitCreate(e, transaction.id)} className="d-flex align-items-end gap-2">
                                                <div style={{ flex: 1 }}>
                                                    <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                                        Offset account
                                                    </label>
                                                    <AccountPicker
                                                        value={createForm.data.offset_account_id}
                                                        onChange={(id) => createForm.setData('offset_account_id', id)}
                                                        error={createForm.errors.offset_account_id}
                                                        placeholder="e.g. Bank Fees Expense"
                                                    />
                                                </div>
                                                <div style={{ flex: 1 }}>
                                                    <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                                        Description
                                                    </label>
                                                    <input
                                                        type="text"
                                                        className="form-control"
                                                        style={{ borderRadius: 'var(--af-radius-sm)', fontSize: '14px' }}
                                                        value={createForm.data.description}
                                                        onChange={(e) => createForm.setData('description', e.target.value)}
                                                    />
                                                </div>
                                                <Button type="submit" size="sm" leadingIcon={<Check size={14} />} loading={createForm.processing}>
                                                    Post & Match
                                                </Button>
                                                <Button type="button" variant="ghost" size="sm" onClick={() => setActiveRow(null)}>
                                                    Cancel
                                                </Button>
                                            </form>
                                        </td>
                                    </tr>
                                )}
                            </Fragment>
                        ))}
                    </tbody>
                </Table>
            </Card>
        </AppLayout>
    );
}
