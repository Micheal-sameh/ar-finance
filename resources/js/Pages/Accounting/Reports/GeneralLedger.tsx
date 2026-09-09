import { Head, router } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Input } from '@/Components/ui/Input';
import { Table } from '@/Components/ui/Table';
import { AppLayout } from '@/Layouts/AppLayout';
import type { Account, GeneralLedgerReport } from '@/types/finance';

interface Props {
    account: Account | null;
    ledger: GeneralLedgerReport | null;
    filters: { account_id: number | null; from: string | null; to: string | null };
}

export default function GeneralLedger({ account, ledger, filters }: Props) {
    const [accountId, setAccountId] = useState<number | null>(filters.account_id);
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    function applyFilter(e: FormEvent) {
        e.preventDefault();
        router.get(route('reports.general-ledger'), { account_id: accountId, from, to }, { preserveState: true });
    }

    return (
        <AppLayout>
            <Head title="General Ledger" />

            <PageHeader title="General Ledger" subtitle="Running balance for a single account, in posting order." />

            <Card>
                <form onSubmit={applyFilter} className="row g-3 align-items-end mb-4">
                    <div className="col-md-4">
                        <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                            Account
                        </label>
                        <AccountPicker value={accountId} onChange={setAccountId} />
                    </div>
                    <div className="col-md-2">
                        <Input type="date" label="From" value={from} onChange={(e) => setFrom(e.target.value)} />
                    </div>
                    <div className="col-md-2">
                        <Input type="date" label="To" value={to} onChange={(e) => setTo(e.target.value)} />
                    </div>
                    <div className="col-md-2">
                        <Button type="submit" variant="outline">
                            Apply
                        </Button>
                    </div>
                </form>

                {!account || !ledger ? (
                    <EmptyState
                        icon={<BookOpen size={20} />}
                        title="Choose an account"
                        description="Select an account above to see its ledger."
                    />
                ) : (
                    <>
                        <div className="mb-3" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                            {account.code} · {account.name} — normal balance: {account.normal_balance}
                        </div>

                        <Table>
                            <Table.Head>
                                <Table.HeadCell className="ps-3">Date</Table.HeadCell>
                                <Table.HeadCell>Description</Table.HeadCell>
                                <Table.HeadCell>Reference</Table.HeadCell>
                                <Table.HeadCell className="text-end">Debit</Table.HeadCell>
                                <Table.HeadCell className="text-end">Credit</Table.HeadCell>
                                <Table.HeadCell className="text-end pe-3">Balance</Table.HeadCell>
                            </Table.Head>
                            <tbody>
                                {ledger.lines.length === 0 ? (
                                    <Table.Row>
                                        <Table.Cell colSpan={6} className="text-center py-4" style={{ color: 'var(--af-label)' }}>
                                            No posted activity in this range.
                                        </Table.Cell>
                                    </Table.Row>
                                ) : (
                                    ledger.lines.map((line, i) => (
                                        <Table.Row key={i}>
                                            <Table.Cell className="ps-3">{line.date}</Table.Cell>
                                            <Table.Cell>{line.description}</Table.Cell>
                                            <Table.Cell>{line.reference ?? '—'}</Table.Cell>
                                            <Table.Cell className="text-end">
                                                {line.debit > 0 && <MoneyDisplay amount={line.debit} />}
                                            </Table.Cell>
                                            <Table.Cell className="text-end">
                                                {line.credit > 0 && <MoneyDisplay amount={line.credit} />}
                                            </Table.Cell>
                                            <Table.Cell className="text-end pe-3">
                                                <MoneyDisplay amount={line.balance} />
                                            </Table.Cell>
                                        </Table.Row>
                                    ))
                                )}
                                <Table.Row style={{ fontWeight: 600 }}>
                                    <Table.Cell className="ps-3" colSpan={5}>
                                        Ending balance
                                    </Table.Cell>
                                    <Table.Cell className="text-end pe-3">
                                        <MoneyDisplay amount={ledger.ending_balance} />
                                    </Table.Cell>
                                </Table.Row>
                            </tbody>
                        </Table>
                    </>
                )}
            </Card>
        </AppLayout>
    );
}
