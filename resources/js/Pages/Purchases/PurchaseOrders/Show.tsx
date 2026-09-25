import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { useConfirm } from '@/Components/ui/ConfirmProvider';
import { Input } from '@/Components/ui/Input';
import { Table } from '@/Components/ui/Table';
import type { PurchaseOrder, PurchaseOrderStatus } from '@/types/finance';
import { formatDate } from '@/utils/finance';

interface Props {
    purchaseOrder: PurchaseOrder;
}

function statusVariant(status: PurchaseOrderStatus) {
    return { draft: 'neutral', sent: 'info', closed: 'success', cancelled: 'danger' }[status] as
        | 'neutral'
        | 'info'
        | 'success'
        | 'danger';
}

function lineTotal(line: PurchaseOrder['lines'][number]): number {
    return parseFloat(line.quantity) * parseFloat(line.unit_price);
}

export default function PurchaseOrdersShow({ purchaseOrder }: Props) {
    const confirm = useConfirm();
    const [showConvertForm, setShowConvertForm] = useState(false);
    const total = purchaseOrder.lines.reduce((sum, line) => sum + lineTotal(line), 0);
    const isOpen = purchaseOrder.status === 'draft' || purchaseOrder.status === 'sent';

    const convertForm = useForm({
        bill_number: '',
        bill_date: new Date().toISOString().slice(0, 10),
        due_date: new Date().toISOString().slice(0, 10),
        payable_account_id: null as number | null,
    });

    function send() {
        router.post(route('purchase-orders.send', purchaseOrder.id));
    }

    async function cancelPo() {
        if (await confirm('Cancel this purchase order?', { variant: 'danger', confirmLabel: 'Cancel PO' })) {
            router.post(route('purchase-orders.cancel', purchaseOrder.id));
        }
    }

    function convertToBill(e: FormEvent) {
        e.preventDefault();
        convertForm.post(route('purchase-orders.convert-to-bill', purchaseOrder.id));
    }

    return (
        <>
            <Head title={purchaseOrder.po_number} />

            <PageHeader
                title={purchaseOrder.po_number}
                subtitle={purchaseOrder.vendor?.name}
                action={
                    <div className="d-flex align-items-center gap-2">
                        <Badge variant={statusVariant(purchaseOrder.status)}>{purchaseOrder.status}</Badge>
                        {isOpen && (
                            <>
                                {purchaseOrder.status === 'draft' && (
                                    <Button variant="outline" onClick={send}>
                                        Send
                                    </Button>
                                )}
                                <Button variant="outline" onClick={cancelPo}>
                                    Cancel
                                </Button>
                                <Button onClick={() => setShowConvertForm(true)}>Convert to Bill</Button>
                            </>
                        )}
                    </div>
                }
            />

            {showConvertForm && (
                <Card className="mb-3">
                    <form onSubmit={convertToBill} className="row g-3 align-items-end">
                        <div className="col-md-3">
                            <Input
                                label="Bill #"
                                value={convertForm.data.bill_number}
                                onChange={(e) => convertForm.setData('bill_number', e.target.value)}
                                error={convertForm.errors.bill_number}
                                placeholder="BILL-0001"
                            />
                        </div>
                        <div className="col-md-2">
                            <Input
                                type="date"
                                label="Bill date"
                                value={convertForm.data.bill_date}
                                onChange={(e) => convertForm.setData('bill_date', e.target.value)}
                                error={convertForm.errors.bill_date}
                            />
                        </div>
                        <div className="col-md-2">
                            <Input
                                type="date"
                                label="Due date"
                                value={convertForm.data.due_date}
                                onChange={(e) => convertForm.setData('due_date', e.target.value)}
                                error={convertForm.errors.due_date}
                            />
                        </div>
                        <div className="col-md-3">
                            <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                Payable account
                            </label>
                            <AccountPicker
                                value={convertForm.data.payable_account_id}
                                onChange={(id) => convertForm.setData('payable_account_id', id)}
                                error={convertForm.errors.payable_account_id}
                                placeholder="e.g. Accounts Payable"
                            />
                        </div>
                        <div className="col-md-2 d-flex gap-2">
                            <Button type="submit" loading={convertForm.processing}>
                                Create Bill
                            </Button>
                            <Button type="button" variant="ghost" onClick={() => setShowConvertForm(false)}>
                                Cancel
                            </Button>
                        </div>
                    </form>
                </Card>
            )}

            <Card padded={false}>
                <div className="row g-3 p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <div className="col-md-4">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Order date</div>
                        <div>{formatDate(purchaseOrder.order_date)}</div>
                    </div>
                    <div className="col-md-4">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Expected date</div>
                        <div>{formatDate(purchaseOrder.expected_date)}</div>
                    </div>
                </div>

                <Table>
                    <Table.Head>
                        <Table.HeadCell className="ps-3">Description</Table.HeadCell>
                        <Table.HeadCell className="text-end">Qty</Table.HeadCell>
                        <Table.HeadCell className="text-end">Unit price</Table.HeadCell>
                        <Table.HeadCell>Account</Table.HeadCell>
                        <Table.HeadCell className="text-end pe-3">Total</Table.HeadCell>
                    </Table.Head>
                    <tbody>
                        {purchaseOrder.lines.map((line) => (
                            <Table.Row key={line.id}>
                                <Table.Cell className="ps-3">{line.description}</Table.Cell>
                                <Table.Cell className="text-end">{line.quantity}</Table.Cell>
                                <Table.Cell className="text-end">
                                    <MoneyDisplay amount={line.unit_price} />
                                </Table.Cell>
                                <Table.Cell>{line.account ? `${line.account.code} · ${line.account.name}` : '—'}</Table.Cell>
                                <Table.Cell className="text-end pe-3">
                                    <MoneyDisplay amount={lineTotal(line)} />
                                </Table.Cell>
                            </Table.Row>
                        ))}
                        <Table.Row style={{ fontWeight: 600 }}>
                            <Table.Cell className="ps-3" colSpan={4}>
                                Total
                            </Table.Cell>
                            <Table.Cell className="text-end pe-3">
                                <MoneyDisplay amount={total} />
                            </Table.Cell>
                        </Table.Row>
                    </tbody>
                </Table>
            </Card>
        </>
    );
}
