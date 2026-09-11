import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { CostCenterPicker } from '@/Components/finance/CostCenterPicker';
import { emptyLineItem, LineItemEditor, type LineItemInput } from '@/Components/finance/LineItemEditor';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import { AppLayout } from '@/Layouts/AppLayout';
import type { Vendor } from '@/types/finance';

interface Props {
    vendors: Vendor[];
}

export default function BillsCreate({ vendors }: Props) {
    const form = useForm<{
        vendor_id: number | '';
        bill_number: string;
        bill_date: string;
        due_date: string;
        payable_account_id: number | null;
        tax_receivable_account_id: number | null;
        cost_center_id: number | null;
        lines: LineItemInput[];
    }>({
        vendor_id: '',
        bill_number: '',
        bill_date: new Date().toISOString().slice(0, 10),
        due_date: new Date().toISOString().slice(0, 10),
        payable_account_id: null,
        tax_receivable_account_id: null,
        cost_center_id: null,
        lines: [emptyLineItem()],
    });

    const hasTax = form.data.lines.some((line) => parseFloat(line.tax_rate) > 0);

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(route('bills.store'));
    }

    return (
        <AppLayout>
            <Head title="New Bill" />

            <PageHeader title="New Bill" subtitle="Saved as a draft — approving it posts spend to the ledger." />

            <form onSubmit={submit}>
                <Card>
                    <div className="row g-3 mb-4">
                        <div className="col-md-3">
                            <Select
                                label="Vendor"
                                value={form.data.vendor_id}
                                onChange={(e) => form.setData('vendor_id', e.target.value ? Number(e.target.value) : '')}
                                error={form.errors.vendor_id}
                            >
                                <option value="">Select vendor…</option>
                                {vendors.map((vendor) => (
                                    <option key={vendor.id} value={vendor.id}>
                                        {vendor.name}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div className="col-md-2">
                            <Input
                                label="Bill #"
                                value={form.data.bill_number}
                                onChange={(e) => form.setData('bill_number', e.target.value)}
                                error={form.errors.bill_number}
                                placeholder="BILL-0001"
                            />
                        </div>
                        <div className="col-md-2">
                            <Input
                                type="date"
                                label="Bill date"
                                value={form.data.bill_date}
                                onChange={(e) => form.setData('bill_date', e.target.value)}
                                error={form.errors.bill_date}
                            />
                        </div>
                        <div className="col-md-2">
                            <Input
                                type="date"
                                label="Due date"
                                value={form.data.due_date}
                                onChange={(e) => form.setData('due_date', e.target.value)}
                                error={form.errors.due_date}
                            />
                        </div>
                        <div className="col-md-3">
                            <CostCenterPicker
                                label="Cost center (optional)"
                                value={form.data.cost_center_id}
                                onChange={(id) => form.setData('cost_center_id', id)}
                                error={form.errors.cost_center_id}
                            />
                        </div>
                    </div>

                    <div className="row g-3 mb-4">
                        <div className="col-md-6">
                            <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                Payable account
                            </label>
                            <AccountPicker
                                value={form.data.payable_account_id}
                                onChange={(id) => form.setData('payable_account_id', id)}
                                error={form.errors.payable_account_id}
                                placeholder="e.g. Accounts Payable"
                            />
                        </div>
                        {hasTax && (
                            <div className="col-md-6">
                                <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                    Tax receivable account
                                </label>
                                <AccountPicker
                                    value={form.data.tax_receivable_account_id}
                                    onChange={(id) => form.setData('tax_receivable_account_id', id)}
                                    error={form.errors.tax_receivable_account_id}
                                    placeholder="e.g. VAT Receivable"
                                />
                            </div>
                        )}
                    </div>

                    <LineItemEditor
                        lines={form.data.lines}
                        onChange={(lines) => form.setData('lines', lines)}
                        accountLabel="Expense account"
                        showTax
                        errors={form.errors as Record<string, string>}
                    />

                    <div className="d-flex justify-content-end mt-4 pt-3" style={{ borderTop: '1px solid var(--af-border)' }}>
                        <Button type="submit" loading={form.processing}>
                            Save Draft
                        </Button>
                    </div>
                </Card>
            </form>
        </AppLayout>
    );
}
