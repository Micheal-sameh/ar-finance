import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { Table } from '@/Components/ui/Table';

export interface AgingRow {
    id: number;
    name: string;
    current: number;
    days_1_30: number;
    days_31_60: number;
    days_61_90: number;
    days_90_plus: number;
    total: number;
}

export interface AgingTotals {
    current: number;
    days_1_30: number;
    days_31_60: number;
    days_61_90: number;
    days_90_plus: number;
    total: number;
}

interface AgingTableProps {
    nameHeader: string;
    rows: AgingRow[];
    totals: AgingTotals;
    currency: string;
}

/**
 * Shared shape behind both AR Aging (by client) and AP Aging (by vendor)
 * — structurally identical, just a different name column and data source.
 */
export function AgingTable({ nameHeader, rows, totals, currency }: AgingTableProps) {
    return (
        <Table>
            <Table.Head>
                <Table.HeadCell className="ps-3">{nameHeader}</Table.HeadCell>
                <Table.HeadCell className="text-end">Current</Table.HeadCell>
                <Table.HeadCell className="text-end">1-30 days</Table.HeadCell>
                <Table.HeadCell className="text-end">31-60 days</Table.HeadCell>
                <Table.HeadCell className="text-end">61-90 days</Table.HeadCell>
                <Table.HeadCell className="text-end">90+ days</Table.HeadCell>
                <Table.HeadCell className="text-end pe-3">Total</Table.HeadCell>
            </Table.Head>
            <tbody>
                {rows.map((row) => (
                    <Table.Row key={row.id}>
                        <Table.Cell className="ps-3" style={{ fontWeight: 600 }}>
                            {row.name}
                        </Table.Cell>
                        <Table.Cell className="text-end">
                            <MoneyDisplay amount={row.current} currency={currency} />
                        </Table.Cell>
                        <Table.Cell className="text-end">
                            <MoneyDisplay amount={row.days_1_30} currency={currency} />
                        </Table.Cell>
                        <Table.Cell className="text-end">
                            <MoneyDisplay amount={row.days_31_60} currency={currency} />
                        </Table.Cell>
                        <Table.Cell className="text-end">
                            <span style={{ color: row.days_61_90 > 0 ? 'var(--af-warning)' : undefined }}>
                                <MoneyDisplay amount={row.days_61_90} currency={currency} />
                            </span>
                        </Table.Cell>
                        <Table.Cell className="text-end">
                            <span style={{ color: row.days_90_plus > 0 ? 'var(--af-danger)' : undefined }}>
                                <MoneyDisplay amount={row.days_90_plus} currency={currency} />
                            </span>
                        </Table.Cell>
                        <Table.Cell className="text-end pe-3" style={{ fontWeight: 600 }}>
                            <MoneyDisplay amount={row.total} currency={currency} />
                        </Table.Cell>
                    </Table.Row>
                ))}
                <Table.Row style={{ fontWeight: 700, borderTop: '2px solid var(--af-navy)' }}>
                    <Table.Cell className="ps-3">Total</Table.Cell>
                    <Table.Cell className="text-end">
                        <MoneyDisplay amount={totals.current} currency={currency} />
                    </Table.Cell>
                    <Table.Cell className="text-end">
                        <MoneyDisplay amount={totals.days_1_30} currency={currency} />
                    </Table.Cell>
                    <Table.Cell className="text-end">
                        <MoneyDisplay amount={totals.days_31_60} currency={currency} />
                    </Table.Cell>
                    <Table.Cell className="text-end">
                        <MoneyDisplay amount={totals.days_61_90} currency={currency} />
                    </Table.Cell>
                    <Table.Cell className="text-end">
                        <MoneyDisplay amount={totals.days_90_plus} currency={currency} />
                    </Table.Cell>
                    <Table.Cell className="text-end pe-3">
                        <MoneyDisplay amount={totals.total} currency={currency} />
                    </Table.Cell>
                </Table.Row>
            </tbody>
        </Table>
    );
}
