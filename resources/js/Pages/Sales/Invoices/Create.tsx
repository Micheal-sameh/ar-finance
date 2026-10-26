import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import {
    emptyInvoiceLine,
    InvoiceLineEditor,
    lineSubtotal,
    lineTotal,
    type InvoiceLineInput,
} from '@/Components/finance/InvoiceLineEditor';
import { Icon } from '@/Components/layout/Icon';
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

function formatMoney(value: number, currency: string): string {
    return `${currency} ${value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
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
    const client = clients.find((candidate) => candidate.id === form.data.client_id);
    const subtotal = form.data.lines.reduce((sum, line) => sum + lineSubtotal(line), 0);
    const total = form.data.lines.reduce((sum, line) => sum + lineTotal(line), 0);
    const tax = total - subtotal;
    const daysUntilDue = Math.round((new Date(form.data.due_date).getTime() - new Date(form.data.issue_date).getTime()) / 86_400_000);

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(route('invoices.store'));
    }

    return (
        <>
            <Head title="New Invoice" />

            <div className="page-header">
                <div>
                    <Link href={route('invoices.index')} className="back-link">
                        ← Back to invoices
                    </Link>
                    <h1>New invoice</h1>
                    <p>Saved as a draft — nothing posts to the ledger until you send it.</p>
                </div>
                <div className="header-actions">
                    <Link href={route('invoices.index')} className="button ghost">
                        Cancel
                    </Link>
                    <button type="submit" form="invoice-form" className="button primary" disabled={form.processing}>
                        <Icon name="send" size={16} />
                        {form.processing ? 'Saving...' : 'Save draft'}
                    </button>
                </div>
            </div>

            <form id="invoice-form" onSubmit={submit}>
                <div className="form-layout">
                    <div className="form-main">
                        <article className="card form-card">
                            <h2>Invoice details</h2>
                            <div className="form-grid">
                                <div className="wide">
                                    <Select
                                        label="Customer"
                                        help="Who you are billing. Their details appear on the invoice."
                                        value={form.data.client_id}
                                        onChange={(e) => form.setData('client_id', e.target.value ? Number(e.target.value) : '')}
                                        error={form.errors.client_id}
                                    >
                                        <option value="">Select client…</option>
                                        {clients.map((option) => (
                                            <option key={option.id} value={option.id}>
                                                {option.name}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                                <Input
                                    label="Invoice number"
                                    help="A unique number for this invoice. Customers use it when they pay."
                                    value={form.data.invoice_number}
                                    onChange={(e) => form.setData('invoice_number', e.target.value)}
                                    error={form.errors.invoice_number}
                                    placeholder="INV-0001"
                                />
                                <Select
                                    label="Currency"
                                    help="The currency the customer will pay in. If it differs from your base currency you will be asked for an exchange rate."
                                    value={form.data.currency}
                                    onChange={(e) => form.setData('currency', e.target.value)}
                                    error={form.errors.currency}
                                >
                                    {currencyOptions.map((currency) => (
                                        <option key={currency.code} value={currency.code}>
                                            {currency.code} — {currency.name}
                                        </option>
                                    ))}
                                </Select>
                                <Input
                                    type="date"
                                    label="Issue date"
                                    help="The date shown on the invoice. The sale is recorded on this date."
                                    value={form.data.issue_date}
                                    onChange={(e) => form.setData('issue_date', e.target.value)}
                                    error={form.errors.issue_date}
                                />
                                <Input
                                    type="date"
                                    label="Due date"
                                    help="The date payment is expected by. Anything unpaid after this date is shown as overdue."
                                    value={form.data.due_date}
                                    onChange={(e) => form.setData('due_date', e.target.value)}
                                    error={form.errors.due_date}
                                />
                                {isForeignCurrency && (
                                    <div className="wide">
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
                                )}
                                <div>
                                    <span className="fg-label">Receivable account</span>
                                    <AccountPicker
                                        value={form.data.receivable_account_id}
                                        onChange={(id) => form.setData('receivable_account_id', id)}
                                        error={form.errors.receivable_account_id}
                                        placeholder="e.g. Accounts Receivable"
                                        filterType="asset"
                                    />
                                </div>
                                {hasTax && (
                                    <div>
                                        <span className="fg-label">Tax payable account</span>
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
                        </article>

                        <article className="card line-card">
                            <div className="card-head">
                                <div>
                                    <h2>Line items</h2>
                                    <p>Add products or services to this invoice</p>
                                </div>
                            </div>
                            <InvoiceLineEditor
                                lines={form.data.lines}
                                onChange={(lines) => form.setData('lines', lines)}
                                currency={form.data.currency}
                                errors={form.errors as Record<string, string>}
                            />
                        </article>
                    </div>

                    <aside className="summary-card card">
                        <h2>Invoice summary</h2>
                        <div className="summary-customer">
                            <span>BILL TO</span>
                            <b>{client?.name ?? 'No customer selected'}</b>
                        </div>
                        <div className="summary-lines">
                            <div>
                                <span>Subtotal</span>
                                <span className="money">{formatMoney(subtotal, form.data.currency)}</span>
                            </div>
                            <div>
                                <span>Tax</span>
                                <span className="money">{formatMoney(tax, form.data.currency)}</span>
                            </div>
                            <div className="total">
                                <span>Total</span>
                                <span className="money">{formatMoney(total, form.data.currency)}</span>
                            </div>
                        </div>
                        <div className="summary-note">
                            <Icon name="calendar" />
                            <div>
                                <b>Payment due</b>
                                <span>
                                    {new Date(form.data.due_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}
                                    {daysUntilDue >= 0 ? ` · in ${daysUntilDue} days` : ''}
                                </span>
                            </div>
                        </div>
                    </aside>
                </div>
            </form>
        </>
    );
}
