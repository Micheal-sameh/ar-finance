import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { FilterPanel } from '@/Components/ui/FilterPanel';
import { Input } from '@/Components/ui/Input';
import { Table } from '@/Components/ui/Table';

interface Row {
    tenant_id: number;
    tenant_name: string;
    base_currency: string;
    total_ar: number;
    total_ap: number;
}

interface Props {
    rows: Row[];
    filters: { as_of: string };
}

function money(amount: number, currency: string) {
    return `${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`;
}

export default function PlatformAging({ rows, filters }: Props) {
    const [asOf, setAsOf] = useState(filters.as_of);

    function applyFilter(e: FormEvent) {
        e.preventDefault();
        router.get(route('platform.reports.aging'), { as_of: asOf }, { preserveState: true });
    }

    return (
        <>
            <Head title="Platform AR/AP Aging" />

            <PageHeader title="AR/AP Aging — All Tenants" subtitle="Each tenant's outstanding receivables and payables, compared side by side." />

            <Card>
                <div className="mb-3">
                    <FilterPanel active={false}>
                        <form onSubmit={applyFilter} className="af-filter-bar">
                            <div style={{ maxWidth: '180px' }}>
                                <Input type="date" label="As of" value={asOf} onChange={(e) => setAsOf(e.target.value)} />
                            </div>
                            <Button type="submit" variant="outline">
                                Apply
                            </Button>
                        </form>
                    </FilterPanel>
                </div>

                <Table>
                    <Table.Head>
                        <Table.HeadCell className="ps-3">Tenant</Table.HeadCell>
                        <Table.HeadCell className="text-end">Total AR</Table.HeadCell>
                        <Table.HeadCell className="text-end pe-3">Total AP</Table.HeadCell>
                    </Table.Head>
                    <tbody>
                        {rows.map((row) => (
                            <Table.Row key={row.tenant_id}>
                                <Table.Cell className="ps-3">{row.tenant_name}</Table.Cell>
                                <Table.Cell className="text-end">{money(row.total_ar, row.base_currency)}</Table.Cell>
                                <Table.Cell className="text-end pe-3">{money(row.total_ap, row.base_currency)}</Table.Cell>
                            </Table.Row>
                        ))}
                    </tbody>
                </Table>
            </Card>
        </>
    );
}
