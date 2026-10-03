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
    output_vat: number;
    input_vat: number;
    net_vat_payable: number;
}

interface Props {
    rows: Row[];
    filters: { from: string; to: string };
}

function money(amount: number, currency: string) {
    return `${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`;
}

export default function PlatformVatReturn({ rows, filters }: Props) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    function applyFilter(e: FormEvent) {
        e.preventDefault();
        router.get(route('platform.reports.vat-return'), { from, to }, { preserveState: true });
    }

    return (
        <>
            <Head title="Platform VAT Return" />

            <PageHeader title="VAT Return — All Tenants" subtitle="Each tenant's output/input VAT, compared side by side." />

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
                        <Table.HeadCell className="text-end">Output VAT</Table.HeadCell>
                        <Table.HeadCell className="text-end">Input VAT</Table.HeadCell>
                        <Table.HeadCell className="text-end pe-3">Net VAT Payable</Table.HeadCell>
                    </Table.Head>
                    <tbody>
                        {rows.map((row) => (
                            <Table.Row key={row.tenant_id}>
                                <Table.Cell className="ps-3">{row.tenant_name}</Table.Cell>
                                <Table.Cell className="text-end">{money(row.output_vat, row.base_currency)}</Table.Cell>
                                <Table.Cell className="text-end">{money(row.input_vat, row.base_currency)}</Table.Cell>
                                <Table.Cell className="text-end pe-3">{money(row.net_vat_payable, row.base_currency)}</Table.Cell>
                            </Table.Row>
                        ))}
                    </tbody>
                </Table>
            </Card>
        </>
    );
}
