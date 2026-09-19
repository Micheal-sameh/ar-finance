import { Head, Link, router } from '@inertiajs/react';
import { Landmark, Plus } from 'lucide-react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Table } from '@/Components/ui/Table';
import { AppLayout } from '@/Layouts/AppLayout';
import type { Paginated, PayrollRun, PayrollRunStatus } from '@/types/finance';
import { formatDate } from '@/utils/finance';

interface Props {
    payrollRuns: Paginated<PayrollRun>;
    filters: { status?: string };
}

function statusVariant(status: PayrollRunStatus) {
    return { draft: 'neutral', approved: 'warning', paid: 'success' }[status] as 'neutral' | 'warning' | 'success';
}

function totalNet(run: PayrollRun): number {
    return run.payslips.reduce((sum, p) => sum + parseFloat(p.net_pay), 0);
}

export default function PayrollRunsIndex({ payrollRuns }: Props) {
    return (
        <AppLayout>
            <Head title="Payroll Runs" />

            <PageHeader
                title="Payroll Runs"
                subtitle="Approving posts Payroll Expense against Salaries Payable."
                action={
                    <Link href={route('payroll-runs.create')}>
                        <Button leadingIcon={<Plus size={16} />}>New Payroll Run</Button>
                    </Link>
                }
            />

            <Card padded={false}>
                {payrollRuns.data.length === 0 ? (
                    <EmptyState
                        icon={<Landmark size={20} />}
                        title="No payroll runs yet"
                        description="Create a payroll run to pay your employees."
                        action={
                            <Link href={route('payroll-runs.create')}>
                                <Button>New Payroll Run</Button>
                            </Link>
                        }
                    />
                ) : (
                    <Table>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Period</Table.HeadCell>
                            <Table.HeadCell>Pay date</Table.HeadCell>
                            <Table.HeadCell>Employees</Table.HeadCell>
                            <Table.HeadCell>Status</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Net Pay</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {payrollRuns.data.map((run) => (
                                <Table.Row key={run.id} style={{ cursor: 'pointer' }} onClick={() => router.get(route('payroll-runs.show', run.id))}>
                                    <Table.Cell className="ps-3">{formatDate(run.period_start)} – {formatDate(run.period_end)}</Table.Cell>
                                    <Table.Cell>{formatDate(run.pay_date)}</Table.Cell>
                                    <Table.Cell>{run.payslips.length}</Table.Cell>
                                    <Table.Cell>
                                        <Badge variant={statusVariant(run.status)}>{run.status}</Badge>
                                    </Table.Cell>
                                    <Table.Cell className="text-end pe-3">
                                        <MoneyDisplay amount={totalNet(run)} />
                                    </Table.Cell>
                                </Table.Row>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>
        </AppLayout>
    );
}
