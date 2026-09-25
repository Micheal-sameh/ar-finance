import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { emptyInvoiceLine, InvoiceLineEditor, type InvoiceLineInput } from '@/Components/finance/InvoiceLineEditor';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import type { Client } from '@/types/finance';

interface CurrencyOption {
    code: string;
    name: string;
}

interface Props {
    clients: Client[];
    baseCurrency: string;
    currencyOptions: CurrencyOption[];
}

export default function InvoicesCreate({ clients, baseCurrency, currencyOptions }: Props) {
    const form = useForm<{
        client_id: number | '';
        invoice_number: string;
        issue_date: string;
        due_date: string;
        currency: string;
        exchange_rate: string;
        receivable_account_id: number | null;
        tax_payable_account_id: number | null;
        lines: InvoiceLineInput[];
    }>({
        client_id: '',
        invoice_number: '',
        issue_date: new Date().toISOString().slice(0, 10),
        due_date: new Date().toISOString().slice(0, 10),
        currency: baseCurrency,
        exchange_rate: '1',
        receivable_account_id: null,
        tax_payable_account_id: null,
        lines: [emptyInvoiceLine()],
    });

    const hasTax = form.data.lines.some((line) => parseFloat(line.tax_rate) > 0);
    const isForeignCurrency = form.data.currency.trim().toUpperCase() !== baseCurrency;

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(route('invoices.store'));
    }

    return (
        <>
            <Head title="New Invoice" />

            <PageHeader title="New Invoice" subtitle="Saved as a draft — nothing posts to the ledger until you send it." />

            <form onSubmit={submit}>
                <Card>
                    <div className="row g-3 mb-4">
                        <div className="col-md-4">
                            <Select
                                label="Client"
                                value={form.data.client_id}
                                onChange={(e) => form.setData('client_id', e.target.value ? Number(e.target.value) : '')}
                                error={form.errors.client_id}
                            >
                                <option value="">Select client…</option>
                                {clients.map((client) => (
                                    <option key={client.id} value={client.id}>
                                        {client.name}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div className="col-md-2">
                            <Input
                                label="Invoice #"
                                value={form.data.invoice_number}
                                onChange={(e) => form.setData('invoice_number', e.target.value)}
                                error={form.errors.invoice_number}
                                placeholder="INV-0001"
                            />
                        </div>
                        <div className="col-md-2">
                            <Input
                                type="date"
                                label="Issue date"
                                value={form.data.issue_date}
                                onChange={(e) => form.setData('issue_date', e.target.value)}
                                error={form.errors.issue_date}
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
                        <div className="col-md-2">
                            <Select
                                label="Currency"
                                value={form.data.currency}
                                onChange={(e) => form.setData('currency', e.target.value)}
                                error={form.errors.currency}
                            >
                                {currencyOptions.map((currency) => (
                                    <option key={currency.code} value={currency.code}>
                                        {currency.code}
                                    </option>
                                ))}
                            </Select>
                        </div>
                    </div>

                    {isForeignCurrency && (
                        <div className="row g-3 mb-4">
                            <div className="col-md-3">
                                <Input
                                    type="number"
                                    step="0.000001"
                                    min="0"
                                    label={`Exchange rate (1 ${form.data.currency || 'FX'} = ? base currency)`}
                                    value={form.data.exchange_rate}
                                    onChange={(e) => form.setData('exchange_rate', e.target.value)}
                                    error={form.errors.exchange_rate}
                                    help="Posts to the ledger in your base currency using this rate."
                                />
                            </div>
                        </div>
                    )}

                    <div className="row g-3 mb-4">
                        <div className="col-md-4">
                            <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                Receivable account
                            </label>
                            <AccountPicker
                                value={form.data.receivable_account_id}
                                onChange={(id) => form.setData('receivable_account_id', id)}
                                error={form.errors.receivable_account_id}
                                placeholder="e.g. Accounts Receivable"
                                filterType="asset"
                            />
                        </div>
                        {hasTax && (
                            <div className="col-md-4">
                                <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                                    Tax payable account
                                </label>
                                <AccountPicker
                                    value={form.data.tax_payable_account_id}
                                    onChange={(id) => form.setData('tax_payable_account_id', id)}
                                    error={form.errors.tax_payable_account_id}
                                    placeholder="e.g. VAT Payable"
                                    filterType="liability"
                                />
                            </div>
                        )}
                    </div>

                    <InvoiceLineEditor
                        lines={form.data.lines}
                        onChange={(lines) => form.setData('lines', lines)}
                        currency={form.data.currency}
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
