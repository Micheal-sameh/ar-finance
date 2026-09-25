import { Head, router } from '@inertiajs/react';
import { BookOpen, PiggyBank, Receipt, TrendingDown, TrendingUp, Wallet } from 'lucide-react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
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

interface ActivityRow {
    id: number;
    date: string;
    description: string;
    reference: string | null;
    source_label: string;
    amount: number;
}

interface Summary {
    cash: number;
    receivables: number;
    payables: number;
    revenue_month: number;
    expenses_month: number;
    net_profit_month: number;
    trend: TrendPoint[];
    recent_activity: ActivityRow[];
}

interface Props {
    summary: Summary;
    baseCurrency: string;
}

/** Simple SVG bar chart — no charting library in the project yet. */
function TrendChart({ trend }: { trend: TrendPoint[] }) {
    const max = Math.max(1, ...trend.flatMap((point) => [point.revenue, point.expenses]));
    const chartHeight = 160;
    const barGroupWidth = 100 / trend.length;

    return (
        <div>
            <svg viewBox={`0 0 ${trend.length * 100} ${chartHeight + 24}`} style={{ width: '100%', height: '220px' }} preserveAspectRatio="none">
                {trend.map((point, i) => {
                    const groupX = i * 100;
                    const revenueHeight = (point.revenue / max) * chartHeight;
                    const expenseHeight = (point.expenses / max) * chartHeight;

                    return (
                        <g key={point.month}>
                            <rect
                                x={groupX + 20}
                                y={chartHeight - revenueHeight}
                                width={20}
                                height={revenueHeight}
                                fill="var(--af-success, #16A34A)"
                                rx={2}
                            />
                            <rect
                                x={groupX + 50}
                                y={chartHeight - expenseHeight}
                                width={20}
                                height={expenseHeight}
                                fill="var(--af-danger, #DC2626)"
                                rx={2}
                            />
                            <text
                                x={groupX + barGroupWidth / 2}
                                y={chartHeight + 18}
                                textAnchor="middle"
                                fontSize="10"
                                fill="var(--af-label)"
                            >
                                {point.month}
                            </text>
                        </g>
                    );
                })}
            </svg>
            <div className="d-flex gap-3 justify-content-center" style={{ fontSize: '12px', color: 'var(--af-label)' }}>
                <span className="d-flex align-items-center gap-1">
                    <span style={{ width: 10, height: 10, borderRadius: 2, backgroundColor: 'var(--af-success, #16A34A)', display: 'inline-block' }} />
                    Revenue
                </span>
                <span className="d-flex align-items-center gap-1">
                    <span style={{ width: 10, height: 10, borderRadius: 2, backgroundColor: 'var(--af-danger, #DC2626)', display: 'inline-block' }} />
                    Expenses
                </span>
            </div>
        </div>
    );
}

export default function DashboardIndex({ summary, baseCurrency }: Props) {
    const netProfitPositive = summary.net_profit_month >= 0;

    return (
        <>
            <Head title="Dashboard" />

            <PageHeader title="Dashboard" subtitle="Your financial position at a glance." />

            <div className="row g-3 mb-3">
                <div className="col-12 col-sm-6 col-xl-3">
                    <StatCard
                        label="Cash & Bank"
                        value={`${summary.cash.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${baseCurrency}`}
                        icon={<Wallet size={20} />}
                        color="primary"
                    />
                </div>
                <div className="col-12 col-sm-6 col-xl-3">
                    <StatCard
                        label="Receivables (AR)"
                        value={`${summary.receivables.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${baseCurrency}`}
                        sub="Outstanding from clients"
                        icon={<Receipt size={20} />}
                        color="info"
                    />
                </div>
                <div className="col-12 col-sm-6 col-xl-3">
                    <StatCard
                        label="Payables (AP)"
                        value={`${summary.payables.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${baseCurrency}`}
                        sub="Owed to vendors"
                        icon={<PiggyBank size={20} />}
                        color="warning"
                    />
                </div>
                <div className="col-12 col-sm-6 col-xl-3">
                    <StatCard
                        label="Net Profit (This Month)"
                        value={`${summary.net_profit_month.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${baseCurrency}`}
                        sub={`Revenue ${summary.revenue_month.toLocaleString(undefined, { maximumFractionDigits: 0 })} · Expenses ${summary.expenses_month.toLocaleString(undefined, { maximumFractionDigits: 0 })}`}
                        icon={netProfitPositive ? <TrendingUp size={20} /> : <TrendingDown size={20} />}
                        color={netProfitPositive ? 'success' : 'danger'}
                    />
                </div>
            </div>

            <div className="row g-3">
                <div className="col-12 col-xl-5">
                    <Card>
                        <div style={{ fontSize: '14px', fontWeight: 600, color: 'var(--af-text)', marginBottom: '8px' }}>
                            Revenue vs Expenses — last 6 months
                        </div>
                        <TrendChart trend={summary.trend} />
                    </Card>
                </div>

                <div className="col-12 col-xl-7">
                    <Card padded={false}>
                        <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)', fontSize: '14px', fontWeight: 600, color: 'var(--af-text)' }}>
                            Recent Activity
                        </div>

                        {summary.recent_activity.length === 0 ? (
                            <EmptyState
                                icon={<BookOpen size={20} />}
                                title="No activity yet"
                                description="Posted transactions will show up here."
                            />
                        ) : (
                            <Table>
                                <Table.Head>
                                    <Table.HeadCell className="ps-3">Date</Table.HeadCell>
                                    <Table.HeadCell>Description</Table.HeadCell>
                                    <Table.HeadCell>Source</Table.HeadCell>
                                    <Table.HeadCell className="text-end pe-3">Amount</Table.HeadCell>
                                </Table.Head>
                                <tbody>
                                    {summary.recent_activity.map((entry) => (
                                        <Table.Row
                                            key={entry.id}
                                            style={{ cursor: 'pointer' }}
                                            onClick={() => router.get(route('journals.show', entry.id))}
                                        >
                                            <Table.Cell className="ps-3">{formatDate(entry.date)}</Table.Cell>
                                            <Table.Cell>{entry.description}</Table.Cell>
                                            <Table.Cell>
                                                <Badge variant="neutral">{entry.source_label}</Badge>
                                            </Table.Cell>
                                            <Table.Cell className="text-end pe-3">
                                                <MoneyDisplay amount={entry.amount} currency={baseCurrency} />
                                            </Table.Cell>
                                        </Table.Row>
                                    ))}
                                </tbody>
                            </Table>
                        )}
                    </Card>
                </div>
            </div>
        </>
    );
}
