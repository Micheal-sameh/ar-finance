import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { useConfirm } from '@/Components/ui/ConfirmProvider';
import { Badge } from '@/Components/ui/Badge';
import { Card } from '@/Components/ui/Card';
import { ExportButton } from '@/Components/ui/ExportButton';
import { Table } from '@/Components/ui/Table';
import type { BankAccount, BankReconciliation, BankReconciliationReport, BankReconciliationReportItem } from '@/types/finance';
import { formatDate } from '@/utils/finance';

interface Props {
    bankAccount: BankAccount;
    reconciliation: BankReconciliation | null;
    report: BankReconciliationReport;
}

function ItemsTable({ title, items, currency }: { title: string; items: BankReconciliationReportItem[]; currency: string }) {
    return (
        <Card padded={false} className="mb-3">
            <div className="px-3 pt-3" style={{ fontSize: '13px', fontWeight: 600, color: 'var(--af-navy)' }}>
                {title}
            </div>
            {items.length === 0 ? (
                <div className="px-3 pb-3 pt-2" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                    None
                </div>
            ) : (
                <Table>
                    <Table.Head>
                        <Table.HeadCell className="ps-3">Date</Table.HeadCell>
                        <Table.HeadCell>Description</Table.HeadCell>
                        <Table.HeadCell>Reference</Table.HeadCell>
                        <Table.HeadCell className="text-end pe-3">Amount</Table.HeadCell>
                    </Table.Head>
                    <tbody>
                        {items.map((item) => (
                            <Table.Row key={item.id}>
                                <Table.Cell className="ps-3">{formatDate(item.date)}</Table.Cell>
                                <Table.Cell>{item.description}</Table.Cell>
                                <Table.Cell>{item.reference ?? '—'}</Table.Cell>
                                <Table.Cell className="text-end pe-3">
                                    <MoneyDisplay amount={item.amount} currency={currency} />
                                </Table.Cell>
                            </Table.Row>
                        ))}
                    </tbody>
                </Table>
            )}
        </Card>
    );
}

function SummaryRow({ label, amount, currency, bold = false }: { label: string; amount: number; currency: string; bold?: boolean }) {
    return (
        <div className="d-flex justify-content-between py-1" style={{ fontSize: '14px', fontWeight: bold ? 600 : 400 }}>
            <span>{label}</span>
            <MoneyDisplay amount={amount} currency={currency} />
        </div>
    );
}

export default function BankAccountsReconciliation({ bankAccount, reconciliation, report }: Props) {
    const confirm = useConfirm();

    async function reopen() {
        if (reconciliation && (await confirm('Reopen this reconciliation? Its transactions will be unlocked.', { variant: 'danger', confirmLabel: 'Reopen' }))) {
            router.delete(route('bank-reconciliations.destroy', reconciliation.id));
        }
    }

    const balanced = Math.abs(report.difference) < 0.005;

    return (
        <>
            <Head title="Bank Reconciliation" />

            <PageHeader
                title="Bank Reconciliation"
                subtitle={`${bankAccount.name} · as of ${formatDate(report.statement_date)}`}
                action={
                    <div className="d-flex gap-2">
                        <Link
                            href={route('bank-accounts.show', bankAccount.id)}
                            className="btn d-inline-flex align-items-center gap-2 text-decoration-none"
                            style={{ fontSize: '12.5px', height: '36px' }}
                        >
                            <ArrowLeft size={14} /> Back
                        </Link>
                        {reconciliation && <ExportButton href={route('bank-reconciliations.export', reconciliation.id)} />}
                        {reconciliation && (
                            <Button variant="outline" onClick={reopen}>
                                Reopen
                            </Button>
                        )}
                    </div>
                }
            />

            <div className="mb-3">
                {reconciliation ? <Badge variant="success">Finished</Badge> : <Badge variant="warning">Preview — not finished</Badge>}
            </div>

            <ItemsTable title="Outstanding deposits (in the books, not yet at the bank)" items={report.outstanding_deposits} currency={bankAccount.currency} />
            <ItemsTable title="Outstanding withdrawals (in the books, not yet at the bank)" items={report.outstanding_withdrawals} currency={bankAccount.currency} />

            <Card>
                <SummaryRow label="Statement ending balance" amount={report.statement_ending_balance} currency={bankAccount.currency} />
                <SummaryRow label="Net outstanding items" amount={report.outstanding_net} currency={bankAccount.currency} />
                <hr />
                <SummaryRow label="Adjusted bank balance" amount={report.adjusted_bank_balance} currency={bankAccount.currency} bold />
                <SummaryRow label="Ledger balance" amount={report.ledger_balance} currency={bankAccount.currency} bold />
                <hr />
                <div className="d-flex justify-content-between align-items-center">
                    <span style={{ fontSize: '14px', fontWeight: 600 }}>Difference</span>
                    <span className="d-flex align-items-center gap-2">
                        <MoneyDisplay amount={report.difference} currency={bankAccount.currency} />
                        <Badge variant={balanced ? 'success' : 'danger'}>{balanced ? 'Balanced' : 'Out of balance'}</Badge>
                    </span>
                </div>
            </Card>
        </>
    );
}
