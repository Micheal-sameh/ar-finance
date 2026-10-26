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
import { Input } from '@/Components/ui/Input';
import { InfoTip } from '@/Components/ui/InfoTip';
import { Table } from '@/Components/ui/Table';
import type { Bill } from '@/types/finance';
import { billAmountPaid, billBalanceDue, billIsPayable, billStatusLabel, billStatusVariant, formatDate } from '@/utils/finance';

interface Props {
    bill: Bill;
}

function lineTotal(line: Bill['lines'][number]): number {
    return parseFloat(line.quantity) * parseFloat(line.unit_price) * (1 + parseFloat(line.tax_rate) / 100);
}

export default function BillsShow({ bill }: Props) {
    const confirm = useConfirm();
    const [showPaymentForm, setShowPaymentForm] = useState(false);
    const total = bill.lines.reduce((sum, line) => sum + lineTotal(line), 0);

    const amountPaid = billAmountPaid(bill);
    const balanceDue = billBalanceDue(bill);
    const payments = bill.payments ?? [];

    const paymentForm = useForm({
        payment_account_id: null as number | null,
        amount: '',
        paid_on: new Date().toISOString().slice(0, 10),
        reference: '',
    });

    async function approve() {
        if (await confirm('Approve this bill? This posts the expense to the ledger.', { variant: 'primary', confirmLabel: 'Approve' })) {
            router.post(route('bills.approve', bill.id));
        }
    }

    function recordPayment() {
        paymentForm.post(route('bills.mark-paid', bill.id), {
            onSuccess: () => {
                setShowPaymentForm(false);
                paymentForm.reset('amount', 'reference');
            },
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
                        <Badge variant={billStatusVariant(bill.status)}>{billStatusLabel(bill.status)}</Badge>
                        <ExportButton label="Download PDF" href={route('bills.pdf', bill.id)} target="_blank" />
                        {bill.status === 'draft' && <Button onClick={approve}>Approve</Button>}
                        {billIsPayable(bill) && <Button onClick={() => setShowPaymentForm(true)}>Record Payment</Button>}
                    </div>
                }
            />

            {showPaymentForm && (
                <Card className="mb-3">
                    <div className="row g-3 align-items-end">
                        <div className="col-md-3">
                            <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                Pay from <InfoTip>The bank or cash account the payment is taken from.</InfoTip>
                            </label>
                            <AccountPicker
                                value={paymentForm.data.payment_account_id}
                                onChange={(id) => paymentForm.setData('payment_account_id', id)}
                                error={paymentForm.errors.payment_account_id}
                                placeholder="e.g. Bank account"
                            />
                        </div>
                        <div className="col-md-2">
                            <Input
                                label="Amount"
                                help="How much you are paying now. Leave empty to pay the whole balance due. A smaller amount leaves the bill partially paid."
                                type="number"
                                step="0.01"
                                min="0.01"
                                placeholder={balanceDue.toFixed(2)}
                                value={paymentForm.data.amount}
                                onChange={(e) => paymentForm.setData('amount', e.target.value)}
                                error={paymentForm.errors.amount}
                            />
                        </div>
                        <div className="col-md-2">
                            <Input
                                label="Payment date"
                                help="The day the money left the account. The payment is recorded in the books on this date."
                                type="date"
                                value={paymentForm.data.paid_on}
                                onChange={(e) => paymentForm.setData('paid_on', e.target.value)}
                                error={paymentForm.errors.paid_on}
                            />
                        </div>
                        <div className="col-md-2">
                            <Input
                                label="Reference"
                                help="Optional. A cheque number or transfer id so you can find this payment on the bank statement later."
                                maxLength={100}
                                value={paymentForm.data.reference}
                                onChange={(e) => paymentForm.setData('reference', e.target.value)}
                                error={paymentForm.errors.reference}
                            />
                        </div>
                        <div className="col-md-3 d-flex gap-2 justify-content-end">
                            <Button onClick={recordPayment} loading={paymentForm.processing}>
                                Confirm Payment
                            </Button>
                            <Button variant="ghost" onClick={() => setShowPaymentForm(false)}>
                                Cancel
                            </Button>
                        </div>
                    </div>
                </Card>
            )}

            {bill.status !== 'draft' && (
                <div className="row g-3 mb-3">
                    <div className="col-md-4">
                        <Card>
                            <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Total</div>
                            <div style={{ fontSize: '18px', fontWeight: 600 }}>
                                <MoneyDisplay amount={total} />
                            </div>
                        </Card>
                    </div>
                    <div className="col-md-4">
                        <Card>
                            <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Paid so far</div>
                            <div style={{ fontSize: '18px', fontWeight: 600 }}>
                                <MoneyDisplay amount={amountPaid} />
                            </div>
                        </Card>
                    </div>
                    <div className="col-md-4">
                        <Card>
                            <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Balance due</div>
                            <div style={{ fontSize: '18px', fontWeight: 600 }}>
                                <MoneyDisplay amount={balanceDue} />
                            </div>
                        </Card>
                    </div>
                </div>
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

            {payments.length > 0 && (
                <Card padded={false} className="mt-3">
                    <div className="px-3 pt-3" style={{ fontSize: '13px', fontWeight: 600, color: 'var(--af-navy)' }}>
                        Payments
                    </div>
                    <Table cards>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Date</Table.HeadCell>
                            <Table.HeadCell>Paid from</Table.HeadCell>
                            <Table.HeadCell>Reference</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Amount</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {payments.map((payment) => (
                                <Table.Row key={payment.id}>
                                    <Table.Cell className="ps-3" label="Date">{formatDate(payment.paid_on)}</Table.Cell>
                                    <Table.Cell label="Paid from">
                                        {payment.payment_account ? `${payment.payment_account.code} · ${payment.payment_account.name}` : '—'}
                                    </Table.Cell>
                                    <Table.Cell label="Reference">{payment.reference ?? '—'}</Table.Cell>
                                    <Table.Cell className="text-end pe-3" label="Amount">
                                        <MoneyDisplay amount={payment.amount} />
                                    </Table.Cell>
                                </Table.Row>
                            ))}
                        </tbody>
                    </Table>
                </Card>
            )}
        </>
    );
}
