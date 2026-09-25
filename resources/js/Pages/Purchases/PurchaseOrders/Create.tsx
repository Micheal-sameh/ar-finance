import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { emptyLineItem, LineItemEditor, type LineItemInput } from '@/Components/finance/LineItemEditor';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import type { Vendor } from '@/types/finance';

interface Props {
    vendors: Vendor[];
}

export default function PurchaseOrdersCreate({ vendors }: Props) {
    const form = useForm<{
        vendor_id: number | '';
        po_number: string;
        order_date: string;
        expected_date: string;
        lines: LineItemInput[];
    }>({
        vendor_id: '',
        po_number: '',
        order_date: new Date().toISOString().slice(0, 10),
        expected_date: '',
        lines: [emptyLineItem()],
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(route('purchase-orders.store'));
    }

    return (
        <>
            <Head title="New Purchase Order" />

            <PageHeader title="New Purchase Order" subtitle="Never posts to the ledger — only converting it to a bill does." />

            <form onSubmit={submit}>
                <Card>
                    <div className="row g-3 mb-4">
                        <div className="col-md-4">
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
                        <div className="col-md-3">
                            <Input
                                label="PO #"
                                value={form.data.po_number}
                                onChange={(e) => form.setData('po_number', e.target.value)}
                                error={form.errors.po_number}
                                placeholder="PO-0001"
                            />
                        </div>
                        <div className="col-md-2">
                            <Input
                                type="date"
                                label="Order date"
                                value={form.data.order_date}
                                onChange={(e) => form.setData('order_date', e.target.value)}
                                error={form.errors.order_date}
                            />
                        </div>
                        <div className="col-md-3">
                            <Input
                                type="date"
                                label="Expected date (optional)"
                                value={form.data.expected_date}
                                onChange={(e) => form.setData('expected_date', e.target.value)}
                                error={form.errors.expected_date}
                            />
                        </div>
                    </div>

                    <LineItemEditor
                        lines={form.data.lines}
                        onChange={(lines) => form.setData('lines', lines)}
                        accountLabel="Expected account"
                        errors={form.errors as Record<string, string>}
                    />

                    <div className="d-flex justify-content-end mt-4 pt-3" style={{ borderTop: '1px solid var(--af-border)' }}>
                        <Button type="submit" loading={form.processing}>
                            Save Draft
                        </Button>
                    </div>
                </Card>
            </form>
        </>
    );
}
