import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
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

export default function ExpensesCreate({ vendors }: Props) {
    const form = useForm<{
        description: string;
        account_id: number | null;
        amount: string;
        date: string;
        vendor_id: number | '';
        payable_account_id: number | null;
    }>({
        description: '',
        account_id: null,
        amount: '',
        date: new Date().toISOString().slice(0, 10),
        vendor_id: '',
        payable_account_id: null,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(route('expenses.store'));
    }

    return (
        <AppLayout>
            <Head title="New Expense" />

            <PageHeader title="New Expense" subtitle="Recorded as pending — approving it posts to the ledger." />

            <form onSubmit={submit}>
                <Card>
                    <div className="row g-3 mb-4">
                        <div className="col-md-6">
                            <Input
                                label="Description"
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                                error={form.errors.description}
                                placeholder="e.g. Office supplies"
                            />
                        </div>
                        <div className="col-md-3">
                            <Input
                                type="number"
                                step="0.01"
                                min="0.01"
                                label="Amount"
                                value={form.data.amount}
                                onChange={(e) => form.setData('amount', e.target.value)}
                                error={form.errors.amount}
                                placeholder="0.00"
                            />
                        </div>
                        <div className="col-md-3">
                            <Input
                                type="date"
                                label="Date"
                                value={form.data.date}
                                onChange={(e) => form.setData('date', e.target.value)}
                                error={form.errors.date}
                            />
                        </div>
                    </div>

                    <div className="row g-3 mb-4">
                        <div className="col-md-4">
                            <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                Category (expense account)
                            </label>
                            <AccountPicker
                                value={form.data.account_id}
                                onChange={(id) => form.setData('account_id', id)}
                                error={form.errors.account_id}
                                placeholder="e.g. Office Supplies Expense"
                            />
                        </div>
                        <div className="col-md-4">
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
                        <div className="col-md-4">
                            <Select
                                label="Vendor (optional)"
                                value={form.data.vendor_id}
                                onChange={(e) => form.setData('vendor_id', e.target.value ? Number(e.target.value) : '')}
                                error={form.errors.vendor_id}
                            >
                                <option value="">No vendor</option>
                                {vendors.map((vendor) => (
                                    <option key={vendor.id} value={vendor.id}>
                                        {vendor.name}
                                    </option>
                                ))}
                            </Select>
                        </div>
                    </div>

                    <div className="d-flex justify-content-end pt-3" style={{ borderTop: '1px solid var(--af-border)' }}>
                        <Button type="submit" loading={form.processing}>
                            Save Expense
                        </Button>
                    </div>
                </Card>
            </form>
        </AppLayout>
    );
}
