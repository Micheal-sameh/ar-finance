import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { useConfirm } from '@/Components/ui/ConfirmProvider';
import { ExportButton } from '@/Components/ui/ExportButton';
import { Table } from '@/Components/ui/Table';
import type { Bill, BillStatus } from '@/types/finance';
import { formatDate } from '@/utils/finance';

interface Props {
    bill: Bill;
}

function statusVariant(status: BillStatus) {
    return { draft: 'neutral', approved: 'warning', paid: 'success' }[status] as 'neutral' | 'warning' | 'success';
}

function lineTotal(line: Bill['lines'][number]): number {
    return parseFloat(line.quantity) * parseFloat(line.unit_price) * (1 + parseFloat(line.tax_rate) / 100);
}

export default function BillsShow({ bill }: Props) {
    const confirm = useConfirm();
    const [showPaymentForm, setShowPaymentForm] = useState(false);
    const total = bill.lines.reduce((sum, line) => sum + lineTotal(line), 0);

    const paymentForm = useForm({ payment_account_id: null as number | null });

    async function approve() {
        if (await confirm('Approve this bill? This posts the expense to the ledger.', { variant: 'primary', confirmLabel: 'Approve' })) {
            router.post(route('bills.approve', bill.id));
        }
    }

    function markPaid() {
        paymentForm.post(route('bills.mark-paid', bill.id), {
            onSuccess: () => setShowPaymentForm(false),
        });
    }

    return (
        <>
            <Head title={bill.bill_number} />

            <PageHeader
                title={bill.bill_number}
                subtitle={bill.vendor?.name}
                action={
                    <div className="d-flex align-items-center gap-2">
                        <Badge variant={statusVariant(bill.status)}>{bill.status}</Badge>
                        <ExportButton label="Download PDF" href={route('bills.pdf', bill.id)} target="_blank" />
                        {bill.status === 'draft' && <Button onClick={approve}>Approve</Button>}
                        {bill.status === 'approved' && <Button onClick={() => setShowPaymentForm(true)}>Mark as Paid</Button>}
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
                <div className="row g-3 p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Bill date</div>
                        <div>{formatDate(bill.bill_date)}</div>
                    </div>
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Due date</div>
                        <div>{formatDate(bill.due_date)}</div>
                    </div>
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Payable account</div>
                        <div>{bill.payable_account ? `${bill.payable_account.code} · ${bill.payable_account.name}` : '—'}</div>
                    </div>
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Tax receivable account</div>
                        <div>{bill.tax_receivable_account ? `${bill.tax_receivable_account.code} · ${bill.tax_receivable_account.name}` : '—'}</div>
                    </div>
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>From purchase order</div>
                        <div>{bill.purchase_order?.po_number ?? '—'}</div>
                    </div>
                </div>

                <Table cards>
                    <Table.Head>
                        <Table.HeadCell className="ps-3">Description</Table.HeadCell>
                        <Table.HeadCell className="text-end">Qty</Table.HeadCell>
                        <Table.HeadCell className="text-end">Unit price</Table.HeadCell>
                        <Table.HeadCell className="text-end">Tax %</Table.HeadCell>
                        <Table.HeadCell>Account</Table.HeadCell>
                        <Table.HeadCell className="text-end pe-3">Total</Table.HeadCell>
                    </Table.Head>
                    <tbody>
                        {bill.lines.map((line) => (
                            <Table.Row key={line.id}>
                                <Table.Cell className="ps-3" label="Description">{line.description}</Table.Cell>
                                <Table.Cell className="text-end" label="Qty">{line.quantity}</Table.Cell>
                                <Table.Cell className="text-end" label="Unit price">
                                    <MoneyDisplay amount={line.unit_price} />
                                </Table.Cell>
                                <Table.Cell className="text-end" label="Tax %">{line.tax_rate}%</Table.Cell>
                                <Table.Cell label="Account">{line.account ? `${line.account.code} · ${line.account.name}` : '—'}</Table.Cell>
                                <Table.Cell className="text-end pe-3" label="Total">
                                    <MoneyDisplay amount={lineTotal(line)} />
                                </Table.Cell>
                            </Table.Row>
                        ))}
                        <Table.Row style={{ fontWeight: 600 }}>
                            <Table.Cell className="ps-3" colSpan={5}>
                                Total
                            </Table.Cell>
                            <Table.Cell className="text-end pe-3" label="Total">
                                <MoneyDisplay amount={total} />
                            </Table.Cell>
                        </Table.Row>
                    </tbody>
                </Table>
            </Card>
        </>
    );
}
