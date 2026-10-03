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
    total_revenue: number;
    total_expenses: number;
    net_profit: number;
}

interface Props {
    rows: Row[];
    filters: { from: string; to: string };
}

function money(amount: number, currency: string) {
    return `${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`;
}

export default function PlatformProfitAndLoss({ rows, filters }: Props) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    function applyFilter(e: FormEvent) {
        e.preventDefault();
        router.get(route('platform.reports.profit-and-loss'), { from, to }, { preserveState: true });
    }

    return (
        <>
            <Head title="Platform Profit & Loss" />

            <PageHeader title="Profit & Loss — All Tenants" subtitle="Each tenant's revenue, expenses and net profit, compared side by side." />

            <Card>
                <div className="mb-3">
                    <FilterPanel active={false}>
                        <form onSubmit={applyFilter} className="af-filter-bar">
                            <div style={{ maxWidth: '180px' }}>
                                <Input type="date" label="From" value={from} onChange={(e) => setFrom(e.target.value)} />
                            </div>
                            <div style={{ maxWidth: '180px' }}>
                                <Input type="date" label="To" value={to} onChange={(e) => setTo(e.target.value)} />
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
                        <Table.HeadCell className="text-end">Revenue</Table.HeadCell>
                        <Table.HeadCell className="text-end">Expenses</Table.HeadCell>
                        <Table.HeadCell className="text-end pe-3">Net Profit</Table.HeadCell>
                    </Table.Head>
                    <tbody>
                        {rows.map((row) => (
                            <Table.Row key={row.tenant_id}>
                                <Table.Cell className="ps-3">{row.tenant_name}</Table.Cell>
                                <Table.Cell className="text-end">{money(row.total_revenue, row.base_currency)}</Table.Cell>
                                <Table.Cell className="text-end">{money(row.total_expenses, row.base_currency)}</Table.Cell>
                                <Table.Cell
                                    className="text-end pe-3"
                                    style={{ color: row.net_profit < 0 ? 'var(--af-danger)' : 'var(--af-success)' }}
                                >
                                    {money(row.net_profit, row.base_currency)}
                                </Table.Cell>
                            </Table.Row>
                        ))}
                    </tbody>
                </Table>
            </Card>
        </>
    );
}
