import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { PayslipEditor, type PayslipInput } from '@/Components/finance/PayslipEditor';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Input } from '@/Components/ui/Input';
import { AppLayout } from '@/Layouts/AppLayout';
import type { Employee } from '@/types/finance';

interface Props {
    employees: Employee[];
}

export default function PayrollRunsCreate({ employees }: Props) {
    const form = useForm<{
        period_start: string;
        period_end: string;
        pay_date: string;
        expense_account_id: number | null;
        payable_account_id: number | null;
        deductions_payable_account_id: number | null;
        payslips: PayslipInput[];
    }>({
        period_start: new Date().toISOString().slice(0, 8) + '01',
        period_end: new Date().toISOString().slice(0, 10),
        pay_date: new Date().toISOString().slice(0, 10),
        expense_account_id: null,
        payable_account_id: null,
        deductions_payable_account_id: null,
        payslips: [],
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(route('payroll-runs.store'));
    }

    return (
        <AppLayout>
            <Head title="New Payroll Run" />

            <PageHeader title="New Payroll Run" subtitle="Saved as a draft — approving it posts to the ledger." />

            <form onSubmit={submit}>
                <Card>
                    <div className="row g-3 mb-4">
                        <div className="col-md-3">
                            <Input
                                type="date"
                                label="Period start"
                                value={form.data.period_start}
                                onChange={(e) => form.setData('period_start', e.target.value)}
                                error={form.errors.period_start}
                            />
                        </div>
                        <div className="col-md-3">
                            <Input
                                type="date"
                                label="Period end"
                                value={form.data.period_end}
                                onChange={(e) => form.setData('period_end', e.target.value)}
                                error={form.errors.period_end}
                            />
                        </div>
                        <div className="col-md-3">
                            <Input
                                type="date"
                                label="Pay date"
                                value={form.data.pay_date}
                                onChange={(e) => form.setData('pay_date', e.target.value)}
                                error={form.errors.pay_date}
                            />
                        </div>
                    </div>

                    <div className="row g-3 mb-4">
                        <div className="col-md-4">
                            <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                Payroll expense account
                            </label>
                            <AccountPicker
                                value={form.data.expense_account_id}
                                onChange={(id) => form.setData('expense_account_id', id)}
                                error={form.errors.expense_account_id}
                                placeholder="e.g. Payroll Expense"
                            />
                        </div>
                        <div className="col-md-4">
                            <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                Salaries payable account
                            </label>
                            <AccountPicker
                                value={form.data.payable_account_id}
                                onChange={(id) => form.setData('payable_account_id', id)}
                                error={form.errors.payable_account_id}
                                placeholder="e.g. Salaries Payable"
                            />
                        </div>
                        <div className="col-md-4">
                            <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                Deductions payable (required if any deductions)
                            </label>
                            <AccountPicker
                                value={form.data.deductions_payable_account_id}
                                onChange={(id) => form.setData('deductions_payable_account_id', id)}
                                error={form.errors.deductions_payable_account_id}
                                placeholder="e.g. Payroll Deductions Payable"
                            />
                        </div>
                    </div>

                    <PayslipEditor
                        employees={employees}
                        payslips={form.data.payslips}
                        onChange={(payslips) => form.setData('payslips', payslips)}
                        errors={form.errors as Record<string, string>}
                    />

                    <div className="d-flex justify-content-end mt-4 pt-3" style={{ borderTop: '1px solid var(--af-border)' }}>
                        <Button type="submit" loading={form.processing} disabled={form.data.payslips.length === 0}>
                            Save Draft
                        </Button>
                    </div>
                </Card>
            </form>
        </AppLayout>
    );
}
