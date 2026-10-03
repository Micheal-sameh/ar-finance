import { Head } from '@inertiajs/react';
import { BookOpen, PiggyBank, Receipt, TrendingDown, TrendingUp, Wallet } from 'lucide-react';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { StatCard } from '@/Components/ui/StatCard';
import { Table } from '@/Components/ui/Table';
import { formatDate } from '@/utils/finance';

interface TrendPoint {
    month: string;
    revenue: number;
    expenses: number;
}

interface TenantRow {
    tenant_id: number;
    tenant_name: string;
    base_currency: string;
    cash: number;
    receivables: number;
    payables: number;
    revenue_month: number;
    expenses_month: number;
    net_profit_month: number;
}

interface ActivityRow {
    id: number;
    date: string;
    description: string;
    reference: string | null;
    source_label: string;
    amount: number;
    tenant_name: string;
}

interface Summary {
    totals: {
        cash: number;
        receivables: number;
        payables: number;
        revenue_month: number;
        expenses_month: number;
        net_profit_month: number;
    };
    trend: TrendPoint[];
    by_tenant: TenantRow[];
    recent_activity: ActivityRow[];
    mixed_currencies: boolean;
}

interface Props {
    summary: Summary;
    baseCurrency: string;
}

function money(amount: number, currency: string) {
    return `${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`;
}

export default function PlatformDashboard({ summary, baseCurrency }: Props) {
    const netProfitPositive = summary.totals.net_profit_month >= 0;

    return (
        <>
            <Head title="Platform Dashboard" />

            <PageHeader
                title="Platform Dashboard"
                subtitle={
                    summary.mixed_currencies
                        ? 'Combined totals across all active tenants — tenants use different base currencies, so totals are summed without FX conversion.'
                        : 'Combined totals across all active tenants.'
                }
            />

            <div className="row g-3 mb-3">
                <div className="col-12 col-sm-6 col-xl-3">
                    <StatCard label="Cash & Bank" value={money(summary.totals.cash, baseCurrency)} icon={<Wallet size={20} />} color="primary" />
                </div>
                <div className="col-12 col-sm-6 col-xl-3">
                    <StatCard
                        label="Receivables (AR)"
                        value={money(summary.totals.receivables, baseCurrency)}
                        sub="Outstanding from clients"
                        icon={<Receipt size={20} />}
                        color="info"
                    />
                </div>
                <div className="col-12 col-sm-6 col-xl-3">
                    <StatCard
                        label="Payables (AP)"
                        value={money(summary.totals.payables, baseCurrency)}
                        sub="Owed to vendors"
                        icon={<PiggyBank size={20} />}
                        color="warning"
                    />
                </div>
                <div className="col-12 col-sm-6 col-xl-3">
                    <StatCard
                        label="Net Profit (This Month)"
                        value={money(summary.totals.net_profit_month, baseCurrency)}
                        sub={`Revenue ${summary.totals.revenue_month.toLocaleString(undefined, { maximumFractionDigits: 0 })} · Expenses ${summary.totals.expenses_month.toLocaleString(undefined, { maximumFractionDigits: 0 })}`}
                        icon={netProfitPositive ? <TrendingUp size={20} /> : <TrendingDown size={20} />}
                        color={netProfitPositive ? 'success' : 'danger'}
                    />
                </div>
            </div>

            <Card padded={false} className="mb-3">
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)', fontSize: '14px', fontWeight: 600, color: 'var(--af-text)' }}>
                    By Tenant
                </div>

                {summary.by_tenant.length === 0 ? (
                    <EmptyState icon={<PiggyBank size={20} />} title="No active tenants" description="Create a tenant to see it here." />
                ) : (
                    <Table cards>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Tenant</Table.HeadCell>
                            <Table.HeadCell className="text-end">Cash</Table.HeadCell>
                            <Table.HeadCell className="text-end">Receivables</Table.HeadCell>
                            <Table.HeadCell className="text-end">Payables</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Net Profit (Month)</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {summary.by_tenant.map((row) => (
                                <Table.Row key={row.tenant_id}>
                                    <Table.Cell className="ps-3" label="Tenant">
                                        {row.tenant_name}
                                    </Table.Cell>
                                    <Table.Cell className="text-end" label="Cash">
                                        {money(row.cash, row.base_currency)}
                                    </Table.Cell>
                                    <Table.Cell className="text-end" label="Receivables">
                                        {money(row.receivables, row.base_currency)}
                                    </Table.Cell>
                                    <Table.Cell className="text-end" label="Payables">
                                        {money(row.payables, row.base_currency)}
                                    </Table.Cell>
                                    <Table.Cell
                                        className="text-end pe-3"
                                        label="Net Profit (Month)"
                                        style={{ color: row.net_profit_month < 0 ? 'var(--af-danger)' : 'var(--af-success)' }}
                                    >
                                        {money(row.net_profit_month, row.base_currency)}
                                    </Table.Cell>
                                </Table.Row>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)', fontSize: '14px', fontWeight: 600, color: 'var(--af-text)' }}>
                    Recent Activity
                </div>

                {summary.recent_activity.length === 0 ? (
                    <EmptyState icon={<BookOpen size={20} />} title="No activity yet" description="Posted transactions will show up here." />
                ) : (
                    <Table>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Date</Table.HeadCell>
                            <Table.HeadCell>Tenant</Table.HeadCell>
                            <Table.HeadCell>Description</Table.HeadCell>
                            <Table.HeadCell>Source</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Amount</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {summary.recent_activity.map((entry, index) => (
                                <Table.Row key={`${entry.tenant_name}-${entry.id}-${index}`}>
                                    <Table.Cell className="ps-3">{formatDate(entry.date)}</Table.Cell>
                                    <Table.Cell>
                                        <Badge variant="neutral">{entry.tenant_name}</Badge>
                                    </Table.Cell>
                                    <Table.Cell>{entry.description}</Table.Cell>
                                    <Table.Cell>
                                        <Badge variant="neutral">{entry.source_label}</Badge>
                                    </Table.Cell>
                                    <Table.Cell className="text-end pe-3">
                                        {entry.amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </Table.Cell>
                                </Table.Row>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>
        </>
    );
}
