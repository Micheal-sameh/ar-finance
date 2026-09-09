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
import type { Invoice } from '@/types/finance';
import { invoiceStatusVariant } from '@/utils/finance';

interface Props {
    invoice: Invoice;
}

function lineTotal(line: Invoice['lines'][number]): number {
    return parseFloat(line.quantity) * parseFloat(line.unit_price) * (1 + parseFloat(line.tax_rate) / 100);
}

export default function InvoicesShow({ invoice }: Props) {
    const [showPaymentForm, setShowPaymentForm] = useState(false);
    const total = invoice.lines.reduce((sum, line) => sum + lineTotal(line), 0);

    const paymentForm = useForm({ payment_account_id: null as number | null });

    function send() {
        if (confirm('Send this invoice? This posts revenue to the ledger and it can no longer be edited.')) {
            router.post(route('invoices.send', invoice.id));
        }
    }

    function recordPayment() {
        paymentForm.post(route('invoices.record-payment', invoice.id), {
            onSuccess: () => setShowPaymentForm(false),
        });
    }

    function voidInvoice() {
        if (confirm('Void this draft invoice?')) {
            router.post(route('invoices.void', invoice.id));
        }
    }

    return (
        <AppLayout>
            <Head title={invoice.invoice_number} />

            <PageHeader
                title={invoice.invoice_number}
                subtitle={invoice.client?.name}
                action={
                    <div className="d-flex align-items-center gap-2">
                        <Badge variant={invoiceStatusVariant(invoice.status)}>{invoice.status}</Badge>
                        {invoice.status === 'draft' && (
                            <>
                                <Button variant="outline" onClick={voidInvoice}>
                                    Void
                                </Button>
                                <Button onClick={send}>Send Invoice</Button>
                            </>
                        )}
                        {invoice.status === 'sent' && (
                            <Button onClick={() => setShowPaymentForm(true)}>Record Payment</Button>
                        )}
                    </div>
                }
            />

            {showPaymentForm && (
                <Card className="mb-3">
                    <div className="d-flex align-items-end gap-2">
                        <div style={{ maxWidth: '320px', flex: 1 }}>
                            <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                Deposit into
                            </label>
                            <AccountPicker
                                value={paymentForm.data.payment_account_id}
                                onChange={(id) => paymentForm.setData('payment_account_id', id)}
                                error={paymentForm.errors.payment_account_id}
                                placeholder="e.g. Bank account"
                            />
                        </div>
                        <Button onClick={recordPayment} loading={paymentForm.processing}>
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
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Issue date</div>
                        <div>{invoice.issue_date}</div>
                    </div>
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Due date</div>
                        <div>{invoice.due_date}</div>
                    </div>
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Receivable account</div>
                        <div>{invoice.receivable_account ? `${invoice.receivable_account.code} · ${invoice.receivable_account.name}` : '—'}</div>
                    </div>
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Paid at</div>
                        <div>{invoice.paid_at ?? '—'}</div>
                    </div>
                </div>

                <Table>
                    <Table.Head>
                        <Table.HeadCell className="ps-3">Description</Table.HeadCell>
                        <Table.HeadCell className="text-end">Qty</Table.HeadCell>
                        <Table.HeadCell className="text-end">Unit price</Table.HeadCell>
                        <Table.HeadCell className="text-end">Tax %</Table.HeadCell>
                        <Table.HeadCell>Account</Table.HeadCell>
                        <Table.HeadCell className="text-end pe-3">Total</Table.HeadCell>
                    </Table.Head>
                    <tbody>
                        {invoice.lines.map((line) => (
                            <Table.Row key={line.id}>
                                <Table.Cell className="ps-3">{line.description}</Table.Cell>
                                <Table.Cell className="text-end">{line.quantity}</Table.Cell>
                                <Table.Cell className="text-end">
                                    <MoneyDisplay amount={line.unit_price} currency={invoice.currency} />
                                </Table.Cell>
                                <Table.Cell className="text-end">{line.tax_rate}%</Table.Cell>
                                <Table.Cell>{line.account ? `${line.account.code} · ${line.account.name}` : '—'}</Table.Cell>
                                <Table.Cell className="text-end pe-3">
                                    <MoneyDisplay amount={lineTotal(line)} currency={invoice.currency} />
                                </Table.Cell>
                            </Table.Row>
                        ))}
                        <Table.Row style={{ fontWeight: 600 }}>
                            <Table.Cell className="ps-3" colSpan={5}>
                                Total
                            </Table.Cell>
                            <Table.Cell className="text-end pe-3">
                                <MoneyDisplay amount={total} currency={invoice.currency} />
                            </Table.Cell>
                        </Table.Row>
                    </tbody>
                </Table>
            </Card>
        </AppLayout>
    );
}
