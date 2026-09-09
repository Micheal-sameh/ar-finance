import { DollarSign, Landmark, TrendingUp } from 'lucide-react';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { StatCard } from '@/Components/ui/StatCard';
import { Table } from '@/Components/ui/Table';

/**
 * Living preview of the ui/ primitive library — new pages should compose
 * from these rather than re-deriving styles. Not linked from the app nav;
 * visit /dev/components directly.
 */
export default function Components() {
    return (
        <div style={{ maxWidth: '1000px', margin: '0 auto', padding: '32px 24px' }}>
            <h1 style={{ color: 'var(--af-navy)', fontSize: '22px', fontWeight: 600, marginBottom: '24px' }}>
                Avarewase Finance — ui/ component preview
            </h1>

            <section style={{ marginBottom: '32px' }}>
                <h2 style={{ fontSize: '14px', color: 'var(--af-label)', marginBottom: '12px' }}>Button</h2>
                <Card>
                    <div className="d-flex gap-2 flex-wrap">
                        <Button variant="primary">Primary</Button>
                        <Button variant="outline">Outline</Button>
                        <Button variant="danger">Danger</Button>
                        <Button variant="ghost">Ghost</Button>
                        <Button variant="primary" size="sm">Small</Button>
                        <Button variant="primary" loading>Loading</Button>
                        <Button variant="primary" disabled>Disabled</Button>
                    </div>
                </Card>
            </section>

            <section style={{ marginBottom: '32px' }}>
                <h2 style={{ fontSize: '14px', color: 'var(--af-label)', marginBottom: '12px' }}>Badge</h2>
                <Card>
                    <div className="d-flex gap-2 flex-wrap">
                        <Badge variant="primary">Primary</Badge>
                        <Badge variant="success">Posted</Badge>
                        <Badge variant="warning">Pending</Badge>
                        <Badge variant="danger">Overdue</Badge>
                        <Badge variant="neutral">Draft</Badge>
                        <Badge variant="info">Info</Badge>
                    </div>
                </Card>
            </section>

            <section style={{ marginBottom: '32px' }}>
                <h2 style={{ fontSize: '14px', color: 'var(--af-label)', marginBottom: '12px' }}>StatCard</h2>
                <div className="row g-3">
                    <div className="col-md-4">
                        <StatCard label="Cash on Hand" value="$48,200.00" sub="+12% vs last month" color="success" icon={<DollarSign size={22} />} />
                    </div>
                    <div className="col-md-4">
                        <StatCard label="Total Liabilities" value="$12,050.00" color="warning" icon={<Landmark size={22} />} />
                    </div>
                    <div className="col-md-4">
                        <StatCard label="Net Profit (MTD)" value="$9,340.00" sub="Trial balance: balanced" color="primary" icon={<TrendingUp size={22} />} />
                    </div>
                </div>
            </section>

            <section style={{ marginBottom: '32px' }}>
                <h2 style={{ fontSize: '14px', color: 'var(--af-label)', marginBottom: '12px' }}>Table</h2>
                <Card padded={false}>
                    <Table>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Code</Table.HeadCell>
                            <Table.HeadCell>Name</Table.HeadCell>
                            <Table.HeadCell>Type</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Status</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            <Table.Row>
                                <Table.Cell className="ps-3">1000</Table.Cell>
                                <Table.Cell>Cash</Table.Cell>
                                <Table.Cell>Asset</Table.Cell>
                                <Table.Cell className="text-end pe-3">
                                    <Badge variant="success">Active</Badge>
                                </Table.Cell>
                            </Table.Row>
                            <Table.Row>
                                <Table.Cell className="ps-3">4000</Table.Cell>
                                <Table.Cell>Sales Revenue</Table.Cell>
                                <Table.Cell>Revenue</Table.Cell>
                                <Table.Cell className="text-end pe-3">
                                    <Badge variant="success">Active</Badge>
                                </Table.Cell>
                            </Table.Row>
                        </tbody>
                    </Table>
                </Card>
            </section>

            <section>
                <h2 style={{ fontSize: '14px', color: 'var(--af-label)', marginBottom: '12px' }}>Card</h2>
                <Card>
                    <p className="mb-0" style={{ color: 'var(--af-text)' }}>
                        The bordered white container used everywhere — 12px radius, 1px border, subtle shadow.
                    </p>
                </Card>
            </section>
        </div>
    );
}
