import { Head, Link, router } from '@inertiajs/react';
import { Landmark, Plus } from 'lucide-react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { ExportButton } from '@/Components/ui/ExportButton';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import { Table } from '@/Components/ui/Table';
import type { Paginated, PayrollRun, PayrollRunStatus } from '@/types/finance';
import { formatDate } from '@/utils/finance';

interface Props {
    payrollRuns: Paginated<PayrollRun>;
    filters: { status?: string; from?: string; to?: string };
}

const PAYROLL_RUN_STATUSES: { value: PayrollRunStatus; label: string }[] = [
    { value: 'draft', label: 'Draft' },
    { value: 'approved', label: 'Approved' },
    { value: 'paid', label: 'Paid' },
];

function statusVariant(status: PayrollRunStatus) {
    return { draft: 'neutral', approved: 'warning', paid: 'success' }[status] as 'neutral' | 'warning' | 'success';
}

function totalNet(run: PayrollRun): number {
    return run.payslips.reduce((sum, p) => sum + parseFloat(p.net_pay), 0);
}

export default function PayrollRunsIndex({ payrollRuns, filters }: Props) {
    function runFilters(next: Partial<Props['filters']>) {
        router.get(route('payroll-runs.index'), { ...filters, ...next }, { preserveState: true });
    }

    function clearFilters() {
        router.get(route('payroll-runs.index'), {}, { preserveState: true });
    }

    return (
        <>
            <Head title="Payroll Runs" />

            <PageHeader
                title="Payroll Runs"
                subtitle="Approving posts Payroll Expense against Salaries Payable."
                action={
                    <div className="d-flex gap-2">
                        <ExportButton href={route('payroll-runs.export', filters)} />
                        <Link href={route('payroll-runs.create')}>
                            <Button leadingIcon={<Plus size={16} />}>New Payroll Run</Button>
                        </Link>
                    </div>
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <div className="af-filter-bar">
                        <Select
                            value={filters.status ?? ''}
                            onChange={(e) => runFilters({ status: e.target.value || undefined })}
                            style={{ maxWidth: '150px' }}
                        >
                            <option value="">All statuses</option>
                            {PAYROLL_RUN_STATUSES.map((s) => (
                                <option key={s.value} value={s.value}>
                                    {s.label}
                                </option>
                            ))}
                        </Select>
                        <Input
                            type="date"
                            label="Period from"
                            value={filters.from ?? ''}
                            onChange={(e) => runFilters({ from: e.target.value || undefined })}
                        />
                        <Input
                            type="date"
                            label="Period to"
                            value={filters.to ?? ''}
                            onChange={(e) => runFilters({ to: e.target.value || undefined })}
                        />
                        {(filters.status || filters.from || filters.to) && (
                            <Button type="button" variant="ghost" onClick={clearFilters}>
                                Clear
                            </Button>
                        )}
                    </div>
                </div>

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
        </>
    );
}
