import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { InfoTip } from '@/Components/ui/InfoTip';
import type { GlMappingRole, GlMappingRow } from '@/types/finance';

interface Props {
    rows: GlMappingRow[];
    canManage: boolean;
}

export default function GlMappingIndex({ rows, canManage }: Props) {
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
        </>
    );
}
