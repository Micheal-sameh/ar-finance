import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { FormEvent } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { InfoTip } from '@/Components/ui/InfoTip';
import type { GlMappingRole, GlMappingRow } from '@/types/finance';

type PaymentMethodKey = 'bank_transfer' | 'instapay' | 'pos';

interface PaymentAccountRow {
    method: PaymentMethodKey;
    account_id: number | null;
    label: string;
}

const PAYMENT_METHODS: { value: PaymentMethodKey; label: string }[] = [
    { value: 'bank_transfer', label: 'Bank transfer' },
    { value: 'instapay', label: 'InstaPay' },
    { value: 'pos', label: 'POS' },
];

interface Props {
    rows: GlMappingRow[];
    paymentAccounts: { method: PaymentMethodKey; account_id: number; label: string | null }[];
    canManage: boolean;
}

export default function GlMappingIndex({ rows, paymentAccounts, canManage }: Props) {
    const extraForm = useForm<{ accounts: PaymentAccountRow[] }>({
        accounts: paymentAccounts.map((row) => ({ method: row.method, account_id: row.account_id, label: row.label ?? '' })),
    });

    function setExtra(index: number, change: Partial<PaymentAccountRow>) {
        extraForm.setData('accounts', extraForm.data.accounts.map((row, i) => (i === index ? { ...row, ...change } : row)));
    }

    function submitExtra(e: FormEvent) {
        e.preventDefault();
        extraForm.transform((data) => ({ accounts: data.accounts.filter((row) => row.account_id !== null) }));
        extraForm.put(route('gl-mapping.payment-accounts'), { preserveScroll: true });
    }

    const form = useForm<{ mappings: Record<GlMappingRole, number | null> }>({
        mappings: Object.fromEntries(rows.map((row) => [row.role, row.account_id])) as Record<GlMappingRole, number | null>,
    });

    const groups = rows.reduce<Record<string, GlMappingRow[]>>((carry, row) => {
        (carry[row.group] ??= []).push(row);

        return carry;
    }, {});

    function setAccount(role: GlMappingRole, accountId: number | null) {
        form.setData('mappings', { ...form.data.mappings, [role]: accountId });
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        form.put(route('gl-mapping.update'), { preserveScroll: true });
    }

    return (
        <>
            <Head title="GL Mapping" />

            <PageHeader
                title="GL Mapping"
                subtitle="Choose the ledger account each kind of transaction posts to. New invoices, bills, expenses, payroll runs and assets start with these accounts, and you can still change them on any form."
            />

            <form onSubmit={submit}>
                {Object.entries(groups).map(([group, groupRows]) => (
                    <Card key={group} className="mb-4">
                        <h2 className="mb-3" style={{ fontSize: '16px' }}>
                            {group}
                        </h2>
                        <div className="d-flex flex-column gap-3">
                            {groupRows.map((row) => (
                                <div key={row.role}>
                                    <span className="fg-label">
                                        {row.label} <InfoTip>{row.description}</InfoTip>
                                    </span>
                                    <AccountPicker
                                        value={form.data.mappings[row.role]}
                                        onChange={(id) => setAccount(row.role, id)}
                                        filterType={row.allowed_types.length === 1 ? row.allowed_types[0] : undefined}
                                        error={(form.errors as Record<string, string>)[`mappings.${row.role}`]}
                                        placeholder={canManage ? 'Not set — pick an account' : 'Not set'}
                                        floating
                                    />
                                </div>
                            ))}
                        </div>
                    </Card>
                ))}

                {canManage && (
                    <div className="d-flex justify-content-end">
                        <Button type="submit" loading={form.processing}>
                            Save mapping
                        </Button>
                    </div>
                )}
            </form>

            <form onSubmit={submitExtra} className="mt-4">
                <Card className="mb-4">
                    <h2 className="mb-1" style={{ fontSize: '16px' }}>
                        More accounts per payment method
                    </h2>
                    <p className="mb-3" style={{ color: 'var(--af-label)', fontSize: '13px' }}>
                        The account chosen above is the default. Add other chart-of-accounts accounts here when a bank transfer, InstaPay or POS payment can land in more than one account (for example two bank accounts). Booking and Donations then let the user pick which account received the money.
                    </p>
                    <div className="d-flex flex-column gap-3">
                        {extraForm.data.accounts.length === 0 && <div style={{ color: 'var(--af-label)', fontSize: '13px' }}>No extra accounts yet.</div>}
                        {extraForm.data.accounts.map((row, index) => (
                            <div key={index} className="d-flex gap-2 align-items-end flex-wrap">
                                <div style={{ width: '160px' }}>
                                    <span className="fg-label">Method</span>
                                    <select className="form-select" value={row.method} disabled={!canManage} onChange={(e) => setExtra(index, { method: e.target.value as PaymentMethodKey })}>
                                        {PAYMENT_METHODS.map((method) => (
                                            <option key={method.value} value={method.value}>
                                                {method.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div style={{ flex: '1 1 260px' }}>
                                    <span className="fg-label">Account</span>
                                    <AccountPicker value={row.account_id} onChange={(id) => setExtra(index, { account_id: id })} filterType="asset" placeholder="Pick an account" floating />
                                </div>
                                <div style={{ flex: '1 1 180px' }}>
                                    <span className="fg-label">Name (optional)</span>
                                    <input className="form-control" maxLength={100} value={row.label} disabled={!canManage} placeholder="e.g. CIB main" onChange={(e) => setExtra(index, { label: e.target.value })} />
                                </div>
                                {canManage && (
                                    <Button type="button" variant="outline" aria-label="Remove account" onClick={() => extraForm.setData('accounts', extraForm.data.accounts.filter((_, i) => i !== index))}>
                                        <Trash2 size={16} />
                                    </Button>
                                )}
                            </div>
                        ))}
                        {Object.keys(extraForm.errors).length > 0 && <div style={{ color: 'var(--af-danger)', fontSize: '12px' }}>{Object.values(extraForm.errors)[0]}</div>}
                    </div>
                    {canManage && (
                        <div className="d-flex justify-content-between mt-3">
                            <Button type="button" variant="outline" leadingIcon={<Plus size={16} />} onClick={() => extraForm.setData('accounts', [...extraForm.data.accounts, { method: 'bank_transfer', account_id: null, label: '' }])}>
                                Add account
                            </Button>
                            <Button type="submit" loading={extraForm.processing}>
                                Save payment accounts
                            </Button>
                        </div>
                    )}
                </Card>
            </form>
        </>
    );
}
