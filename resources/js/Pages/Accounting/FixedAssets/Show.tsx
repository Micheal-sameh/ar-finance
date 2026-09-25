import { Head, router } from '@inertiajs/react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Table } from '@/Components/ui/Table';
import type { DepreciationScheduleRow, FixedAsset } from '@/types/finance';
import { formatDate } from '@/utils/finance';

interface Props {
    fixedAsset: FixedAsset;
    schedule: DepreciationScheduleRow[];
}

export default function FixedAssetsShow({ fixedAsset, schedule }: Props) {
    const netBookValue = parseFloat(fixedAsset.cost) - parseFloat(fixedAsset.accumulated_depreciation);
    const fullyDepreciated = parseFloat(fixedAsset.accumulated_depreciation) >= parseFloat(fixedAsset.cost) - parseFloat(fixedAsset.salvage_value) - 0.005;

    function postDepreciation() {
        router.post(route('fixed-assets.post-depreciation', fixedAsset.id));
    }

    return (
        <>
            <Head title={fixedAsset.name} />

            <PageHeader
                title={fixedAsset.name}
                subtitle={`Purchased ${formatDate(fixedAsset.purchase_date)} · ${fixedAsset.useful_life_years}-year straight-line`}
                action={
                    fullyDepreciated ? (
                        <Badge variant="neutral">Fully depreciated</Badge>
                    ) : (
                        <Button onClick={postDepreciation}>Post Depreciation</Button>
                    )
                }
            />

            <div className="row g-3 mb-4">
                <div className="col-md-3">
                    <Card>
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Cost</div>
                        <div style={{ fontSize: '18px', fontWeight: 600 }}>
                            <MoneyDisplay amount={fixedAsset.cost} />
                        </div>
                    </Card>
                </div>
                <div className="col-md-3">
                    <Card>
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Accumulated Depreciation</div>
                        <div style={{ fontSize: '18px', fontWeight: 600 }}>
                            <MoneyDisplay amount={fixedAsset.accumulated_depreciation} />
                        </div>
                    </Card>
                </div>
                <div className="col-md-3">
                    <Card>
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Net Book Value</div>
                        <div style={{ fontSize: '18px', fontWeight: 600 }}>
                            <MoneyDisplay amount={netBookValue} />
                        </div>
                    </Card>
                </div>
                <div className="col-md-3">
                    <Card>
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Salvage Value</div>
                        <div style={{ fontSize: '18px', fontWeight: 600 }}>
                            <MoneyDisplay amount={fixedAsset.salvage_value} />
                        </div>
                    </Card>
                </div>
            </div>

            <Card padded={false}>
                <div className="row g-3 p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <div className="col-md-4">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Asset account</div>
                        <div>{fixedAsset.asset_account ? `${fixedAsset.asset_account.code} · ${fixedAsset.asset_account.name}` : '—'}</div>
                    </div>
                    <div className="col-md-4">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Depreciation expense account</div>
                        <div>{fixedAsset.depreciation_account ? `${fixedAsset.depreciation_account.code} · ${fixedAsset.depreciation_account.name}` : '—'}</div>
                    </div>
                    <div className="col-md-4">
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Accumulated depreciation account</div>
                        <div>
                            {fixedAsset.accumulated_depreciation_account
                                ? `${fixedAsset.accumulated_depreciation_account.code} · ${fixedAsset.accumulated_depreciation_account.name}`
                                : '—'}
                        </div>
                    </div>
                </div>

                <div className="px-3 pt-3" style={{ fontSize: '13px', fontWeight: 600, color: 'var(--af-navy)' }}>
                    Depreciation Schedule
                </div>

                <Table>
                    <Table.Head>
                        <Table.HeadCell className="ps-3">Month</Table.HeadCell>
                        <Table.HeadCell className="text-end">Amount</Table.HeadCell>
                        <Table.HeadCell className="text-end">Cumulative</Table.HeadCell>
                        <Table.HeadCell className="text-end">Book Value</Table.HeadCell>
                        <Table.HeadCell className="pe-3">Status</Table.HeadCell>
                    </Table.Head>
                    <tbody>
                        {schedule.map((row) => (
                            <Table.Row key={row.month}>
                                <Table.Cell className="ps-3">{row.month}</Table.Cell>
                                <Table.Cell className="text-end">
                                    <MoneyDisplay amount={row.amount} />
                                </Table.Cell>
                                <Table.Cell className="text-end">
                                    <MoneyDisplay amount={row.cumulative} />
                                </Table.Cell>
                                <Table.Cell className="text-end">
                                    <MoneyDisplay amount={row.book_value} />
                                </Table.Cell>
                                <Table.Cell className="pe-3">
                                    <Badge variant={row.posted ? 'success' : 'neutral'}>{row.posted ? 'Posted' : 'Projected'}</Badge>
                                </Table.Cell>
                            </Table.Row>
                        ))}
                    </tbody>
                </Table>
            </Card>
        </>
    );
}
