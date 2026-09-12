import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { useConfirm } from '@/Components/ui/ConfirmProvider';
import { AppLayout } from '@/Layouts/AppLayout';
import type { Expense } from '@/types/finance';
import { expenseStatusVariant } from '@/utils/finance';

interface Props {
    expense: Expense;
}

export default function ExpensesShow({ expense }: Props) {
    const confirm = useConfirm();
    const [showPaymentForm, setShowPaymentForm] = useState(false);
    const paymentForm = useForm({ payment_account_id: null as number | null });

    async function approve() {
        if (await confirm('Approve this expense? This posts it to the ledger.', { variant: 'primary', confirmLabel: 'Approve' })) {
            router.post(route('expenses.approve', expense.id));
        }
    }

    function markPaid() {
        paymentForm.post(route('expenses.mark-paid', expense.id), {
            onSuccess: () => setShowPaymentForm(false),
        });
    }

    return (
        <AppLayout>
            <Head title={expense.description} />

            <PageHeader
                title={expense.description}
                subtitle={`${expense.date}${expense.vendor ? ` · ${expense.vendor.name}` : ''}`}
                action={
                    <div className="d-flex align-items-center gap-2">
                        <Badge variant={expenseStatusVariant(expense.status)}>{expense.status}</Badge>
                        {expense.status === 'pending' && <Button onClick={approve}>Approve</Button>}
                        {expense.status === 'approved' && <Button onClick={() => setShowPaymentForm(true)}>Mark as Paid</Button>}
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

            <Card>
                <div className="row g-3">
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Amount</div>
                        <div style={{ fontSize: '18px', fontWeight: 600 }}>
                            <MoneyDisplay amount={expense.amount} />
                        </div>
                    </div>
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Category</div>
                        <div>{expense.account ? `${expense.account.code} · ${expense.account.name}` : '—'}</div>
                    </div>
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Payable account</div>
                        <div>{expense.payable_account ? `${expense.payable_account.code} · ${expense.payable_account.name}` : '—'}</div>
                    </div>
                    <div className="col-md-3">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Paid at</div>
                        <div>{expense.paid_at ?? '—'}</div>
                    </div>
                </div>
            </Card>
        </AppLayout>
    );
}
