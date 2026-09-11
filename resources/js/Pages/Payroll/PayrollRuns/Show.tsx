import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Table } from '@/Components/ui/Table';
import { AppLayout } from '@/Layouts/AppLayout';
import type { PayrollRun, PayrollRunStatus } from '@/types/finance';

interface Props {
    payrollRun: PayrollRun;
}

function statusVariant(status: PayrollRunStatus) {
    return { draft: 'neutral', approved: 'warning', paid: 'success' }[status] as 'neutral' | 'warning' | 'success';
}

export default function PayrollRunsShow({ payrollRun }: Props) {
    const [showPaymentForm, setShowPaymentForm] = useState(false);
    const paymentForm = useForm({ payment_account_id: null as number | null });

    const totalGross = payrollRun.payslips.reduce((sum, p) => sum + parseFloat(p.gross_pay), 0);
    const totalDeductions = payrollRun.payslips.reduce((sum, p) => sum + parseFloat(p.deductions), 0);
    const totalNet = payrollRun.payslips.reduce((sum, p) => sum + parseFloat(p.net_pay), 0);

    function approve() {
        if (confirm('Approve this payroll run? This posts payroll expense to the ledger.')) {
            router.post(route('payroll-runs.approve', payrollRun.id));
        }
    }

    function markPaid() {
        paymentForm.post(route('payroll-runs.mark-paid', payrollRun.id), {
            onSuccess: () => setShowPaymentForm(false),
        });
    }

    return (
        <AppLayout>
            <Head title={`Payroll ${payrollRun.period_start} – ${payrollRun.period_end}`} />

            <PageHeader
                title={`Payroll: ${payrollRun.period_start} – ${payrollRun.period_end}`}
                subtitle={`Pay date ${payrollRun.pay_date}`}
                action={
                    <div className="d-flex align-items-center gap-2">
                        <Badge variant={statusVariant(payrollRun.status)}>{payrollRun.status}</Badge>
                        {payrollRun.status === 'draft' && <Button onClick={approve}>Approve</Button>}
                        {payrollRun.status === 'approved' && <Button onClick={() => setShowPaymentForm(true)}>Mark as Paid</Button>}
                    </div>
                }
            />

            {showPaymentForm && (
                <Card className="mb-3">
                    <div className="d-flex align-items-end gap-2">
                        <div style={{ maxWidth: '320px', flex: 1 }}>
                            <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                Pay from
                            </label>
                            <AccountPicker
                                value={paymentForm.data.payment_account_id}
                                onChange={(id) => paymentForm.setData('payment_account_id', id)}
                                error={paymentForm.errors.payment_account_id}
                                placeholder="e.g. Bank account"
                            />
                        </div>
                        <Button onClick={markPaid} loading={paymentForm.processing}>
                            Confirm Payment
                        </Button>
                        <Button variant="ghost" onClick={() => setShowPaymentForm(false)}>
                            Cancel
                        </Button>
                    </div>
                </Card>
            )}

            <Card padded={false}>
                <Table>
                    <Table.Head>
                        <Table.HeadCell className="ps-3">Employee</Table.HeadCell>
                        <Table.HeadCell className="text-end">Gross Pay</Table.HeadCell>
                        <Table.HeadCell className="text-end">Deductions</Table.HeadCell>
                        <Table.HeadCell className="text-end pe-3">Net Pay</Table.HeadCell>
                    </Table.Head>
                    <tbody>
                        {payrollRun.payslips.map((payslip) => (
                            <Table.Row key={payslip.id}>
                                <Table.Cell className="ps-3">{payslip.employee?.name ?? '—'}</Table.Cell>
                                <Table.Cell className="text-end">
                                    <MoneyDisplay amount={payslip.gross_pay} />
                                </Table.Cell>
                                <Table.Cell className="text-end">
                                    <MoneyDisplay amount={payslip.deductions} />
                                </Table.Cell>
                                <Table.Cell className="text-end pe-3">
                                    <MoneyDisplay amount={payslip.net_pay} />
                                </Table.Cell>
                            </Table.Row>
                        ))}
                        <Table.Row style={{ fontWeight: 600 }}>
                            <Table.Cell className="ps-3">Total</Table.Cell>
                            <Table.Cell className="text-end">
                                <MoneyDisplay amount={totalGross} />
                            </Table.Cell>
                            <Table.Cell className="text-end">
                                <MoneyDisplay amount={totalDeductions} />
                            </Table.Cell>
                            <Table.Cell className="text-end pe-3">
                                <MoneyDisplay amount={totalNet} />
                            </Table.Cell>
                        </Table.Row>
                    </tbody>
                </Table>
            </Card>
        </AppLayout>
    );
}
