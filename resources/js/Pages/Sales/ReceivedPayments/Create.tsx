import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { InfoTip } from '@/Components/ui/InfoTip';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import type { Client } from '@/types/finance';

interface Props {
    clients: Client[];
}

export default function ReceivedPaymentsCreate({ clients }: Props) {
    const form = useForm<{
        description: string;
        amount: string;
        received_on: string;
        deposit_account_id: number | null;
        credit_account_id: number | null;
        client_id: number | '';
        reference: string;
    }>({
        description: '',
        amount: '',
        received_on: new Date().toISOString().slice(0, 10),
        deposit_account_id: null,
        credit_account_id: null,
        client_id: '',
        reference: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(route('received-payments.store'));
    }

    return (
        <>
            <Head title="Receive Payment" />

            <PageHeader title="Receive Payment" subtitle="Money received without an invoice — it posts to the ledger straight away." />

            <form onSubmit={submit}>
                <Card>
                    <div className="row g-3 mb-4">
                        <div className="col-md-6">
                            <Input
                                label="Description"
                                help="What the money is for, for example Advance for the March event."
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                                error={form.errors.description}
                                placeholder="e.g. Advance payment"
                            />
                        </div>
                        <div className="col-md-3">
                            <Input
                                type="number"
                                step="0.01"
                                min="0.01"
                                label="Amount"
                                help="The amount you received."
                                value={form.data.amount}
                                onChange={(e) => form.setData('amount', e.target.value)}
                                error={form.errors.amount}
                                placeholder="0.00"
                            />
                        </div>
                        <div className="col-md-3">
                            <Input
                                type="date"
                                label="Date received"
                                help="The day the money came in. It can't be in the future."
                                value={form.data.received_on}
                                onChange={(e) => form.setData('received_on', e.target.value)}
                                error={form.errors.received_on}
                            />
                        </div>
                    </div>

                    <div className="row g-3 mb-4">
                        <div className="col-md-4">
                            <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                Received into <InfoTip>The bank or cash account the money was paid into.</InfoTip>
                            </label>
                            <AccountPicker
                                value={form.data.deposit_account_id}
                                onChange={(id) => form.setData('deposit_account_id', id)}
                                error={form.errors.deposit_account_id}
                                filterType="asset"
                                placeholder="e.g. Bank"
                            />
                        </div>
                        <div className="col-md-4">
                            <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                Received for <InfoTip>What the money counts as. Pick a revenue account if it is income, or a liability such as Customer Deposits if it is an advance you still owe service for.</InfoTip>
                            </label>
                            <AccountPicker
                                value={form.data.credit_account_id}
                                onChange={(id) => form.setData('credit_account_id', id)}
                                error={form.errors.credit_account_id}
                                placeholder="e.g. Customer Deposits"
                            />
                        </div>
                        <div className="col-md-4">
                            <Select
                                label="Client (optional)"
                                help="Who paid you, if you want to know later."
                                value={form.data.client_id}
                                onChange={(e) => form.setData('client_id', e.target.value ? Number(e.target.value) : '')}
                                error={form.errors.client_id}
                            >
                                <option value="">No client</option>
                                {clients.map((client) => (
                                    <option key={client.id} value={client.id}>
                                        {client.name}
                                    </option>
                                ))}
                            </Select>
                        </div>
                    </div>

                    <div className="row g-3 mb-4">
                        <div className="col-md-4">
                            <Input
                                label="Reference (optional)"
                                help="A cheque number, transfer id or receipt number."
                                value={form.data.reference}
                                onChange={(e) => form.setData('reference', e.target.value)}
                                error={form.errors.reference}
                            />
                        </div>
                    </div>

                    <div className="d-flex justify-content-end pt-3" style={{ borderTop: '1px solid var(--af-border)' }}>
                        <Button type="submit" loading={form.processing}>
                            Record Payment
                        </Button>
                    </div>
                </Card>
            </form>
        </>
    );
}
