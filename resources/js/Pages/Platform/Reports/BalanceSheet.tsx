import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { FilterPanel } from '@/Components/ui/FilterPanel';
import { Input } from '@/Components/ui/Input';
import { Table } from '@/Components/ui/Table';

interface Row {
    tenant_id: number;
    tenant_name: string;
    base_currency: string;
    total_assets: number;
    total_liabilities: number;
    total_equity: number;
    is_balanced: boolean;
}

interface Props {
    rows: Row[];
    filters: { as_of: string };
}

function money(amount: number, currency: string) {
    return `${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`;
}

export default function PlatformBalanceSheet({ rows, filters }: Props) {
    const [asOf, setAsOf] = useState(filters.as_of);

    function applyFilter(e: FormEvent) {
        e.preventDefault();
        router.get(route('platform.reports.balance-sheet'), { as_of: asOf }, { preserveState: true });
    }

    return (
        <>
            <Head title="Platform Balance Sheet" />

            <PageHeader title="Balance Sheet — All Tenants" subtitle="Each tenant's assets, liabilities and equity, compared side by side." />

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
                        <Table.HeadCell className="text-end">Total Assets</Table.HeadCell>
                        <Table.HeadCell className="text-end">Total Liabilities</Table.HeadCell>
                        <Table.HeadCell className="text-end">Total Equity</Table.HeadCell>
                        <Table.HeadCell className="text-end pe-3">Balanced</Table.HeadCell>
                    </Table.Head>
                    <tbody>
                        {rows.map((row) => (
                            <Table.Row key={row.tenant_id}>
                                <Table.Cell className="ps-3">{row.tenant_name}</Table.Cell>
                                <Table.Cell className="text-end">{money(row.total_assets, row.base_currency)}</Table.Cell>
                                <Table.Cell className="text-end">{money(row.total_liabilities, row.base_currency)}</Table.Cell>
                                <Table.Cell className="text-end">{money(row.total_equity, row.base_currency)}</Table.Cell>
                                <Table.Cell className="text-end pe-3">
                                    <Badge variant={row.is_balanced ? 'success' : 'danger'}>{row.is_balanced ? 'Balanced' : 'Out of balance'}</Badge>
                                </Table.Cell>
                            </Table.Row>
                        ))}
                    </tbody>
                </Table>
            </Card>
        </>
    );
}
