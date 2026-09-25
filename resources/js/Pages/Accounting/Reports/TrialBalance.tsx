import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { BalanceCheck } from '@/Components/finance/BalanceCheck';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { ExportButton } from '@/Components/ui/ExportButton';
import { Input } from '@/Components/ui/Input';
import { Table } from '@/Components/ui/Table';
import type { TrialBalanceReport } from '@/types/finance';

interface Props {
    report: TrialBalanceReport;
    filters: { from: string | null; to: string | null };
}

export default function TrialBalance({ report, filters }: Props) {
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    function applyFilter(e: FormEvent) {
        e.preventDefault();
        router.get(route('reports.trial-balance'), { from, to }, { preserveState: true });
    }

    return (
        <>
            <Head title="Trial Balance" />

            <PageHeader
                title="Trial Balance"
                subtitle="Every posted account balance, derived from the general ledger."
                action={<ExportButton label="Download PDF" href={route('reports.trial-balance.pdf', filters)} target="_blank" />}
            />

            <Card>
                <form onSubmit={applyFilter} className="d-flex align-items-end gap-2 mb-4">
                    <div style={{ maxWidth: '180px' }}>
                        <Input type="date" label="From" value={from} onChange={(e) => setFrom(e.target.value)} />
                    </div>
                    <div style={{ maxWidth: '180px' }}>
                        <Input type="date" label="To" value={to} onChange={(e) => setTo(e.target.value)} />
                    </div>
                    <Button type="submit" variant="outline">
                        Apply
                    </Button>
                    <div className="ms-auto">
                        <BalanceCheck totalDebit={report.total_debit} totalCredit={report.total_credit} />
                    </div>
                </form>

                <Table>
                    <Table.Head>
                        <Table.HeadCell className="ps-3">Code</Table.HeadCell>
                        <Table.HeadCell>Account</Table.HeadCell>
                        <Table.HeadCell>Type</Table.HeadCell>
                        <Table.HeadCell className="text-end">Debit</Table.HeadCell>
                        <Table.HeadCell className="text-end pe-3">Credit</Table.HeadCell>
                    </Table.Head>
                    <tbody>
                        {report.rows.map((row) => (
                            <Table.Row key={row.account_id}>
                                <Table.Cell className="ps-3">{row.code}</Table.Cell>
                                <Table.Cell>{row.name}</Table.Cell>
                                <Table.Cell style={{ textTransform: 'capitalize' }}>{row.type}</Table.Cell>
                                <Table.Cell className="text-end">
                                    {row.debit > 0 && <MoneyDisplay amount={row.debit} />}
                                </Table.Cell>
                                <Table.Cell className="text-end pe-3">
                                    {row.credit > 0 && <MoneyDisplay amount={row.credit} />}
                                </Table.Cell>
                            </Table.Row>
                        ))}
                        <Table.Row style={{ fontWeight: 600 }}>
                            <Table.Cell className="ps-3" colSpan={3}>
                                Total
                            </Table.Cell>
                            <Table.Cell className="text-end">
                                <MoneyDisplay amount={report.total_debit} />
                            </Table.Cell>
                            <Table.Cell className="text-end pe-3">
                                <MoneyDisplay amount={report.total_credit} />
                            </Table.Cell>
                        </Table.Row>
                    </tbody>
                </Table>
            </Card>
        </>
    );
}
