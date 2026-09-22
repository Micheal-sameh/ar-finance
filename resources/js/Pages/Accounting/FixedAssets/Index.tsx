import { Head, Link, router } from '@inertiajs/react';
import { Landmark, Plus, Zap } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { useConfirm } from '@/Components/ui/ConfirmProvider';
import { EmptyState } from '@/Components/ui/EmptyState';
import { ExportButton } from '@/Components/ui/ExportButton';
import { Input } from '@/Components/ui/Input';
import { Table } from '@/Components/ui/Table';
import { AppLayout } from '@/Layouts/AppLayout';
import type { FixedAsset, Paginated } from '@/types/finance';
import { formatDate } from '@/utils/finance';

interface Props {
    fixedAssets: Paginated<FixedAsset>;
    filters: { search?: string };
}

function netBookValue(asset: FixedAsset): number {
    return parseFloat(asset.cost) - parseFloat(asset.accumulated_depreciation);
}

export default function FixedAssetsIndex({ fixedAssets, filters }: Props) {
    const confirm = useConfirm();
    const [search, setSearch] = useState(filters.search ?? '');

    function runSearch(e: FormEvent) {
        e.preventDefault();
        router.get(route('fixed-assets.index'), { search }, { preserveState: true });
    }

    async function runDepreciation() {
        if (
            await confirm("Post this month's depreciation for every asset that hasn't already been posted?", {
                variant: 'primary',
                confirmLabel: 'Run Depreciation',
            })
        ) {
            router.post(route('fixed-assets.run-depreciation'));
        }
    }

    return (
        <AppLayout>
            <Head title="Fixed Assets" />

            <PageHeader
                title="Fixed Assets"
                subtitle="Depreciation register — straight-line, posted monthly."
                action={
                    <div className="d-flex gap-2">
                        <ExportButton href={route('fixed-assets.export', filters)} />
                        <Button variant="outline" leadingIcon={<Zap size={16} />} onClick={runDepreciation}>
                            Run This Month's Depreciation
                        </Button>
                        <Link href={route('fixed-assets.create')}>
                            <Button leadingIcon={<Plus size={16} />}>New Asset</Button>
                        </Link>
                    </div>
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <form onSubmit={runSearch} className="d-flex gap-2" style={{ maxWidth: '320px' }}>
                        <Input placeholder="Search by name…" value={search} onChange={(e) => setSearch(e.target.value)} />
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                    </form>
                </div>

                {fixedAssets.data.length === 0 ? (
                    <EmptyState
                        icon={<Landmark size={20} />}
                        title="No fixed assets yet"
                        description="Add an asset to start tracking its depreciation."
                        action={
                            <Link href={route('fixed-assets.create')}>
                                <Button>New Asset</Button>
                            </Link>
                        }
                    />
                ) : (
                    <Table>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Name</Table.HeadCell>
                            <Table.HeadCell>Purchase date</Table.HeadCell>
                            <Table.HeadCell className="text-end">Cost</Table.HeadCell>
                            <Table.HeadCell className="text-end">Accumulated Depr.</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Net Book Value</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {fixedAssets.data.map((asset) => (
                                <Table.Row key={asset.id} style={{ cursor: 'pointer' }} onClick={() => router.get(route('fixed-assets.show', asset.id))}>
                                    <Table.Cell className="ps-3">{asset.name}</Table.Cell>
                                    <Table.Cell>{formatDate(asset.purchase_date)}</Table.Cell>
                                    <Table.Cell className="text-end">
                                        <MoneyDisplay amount={asset.cost} />
                                    </Table.Cell>
                                    <Table.Cell className="text-end">
                                        <MoneyDisplay amount={asset.accumulated_depreciation} />
                                    </Table.Cell>
                                    <Table.Cell className="text-end pe-3">
                                        <MoneyDisplay amount={netBookValue(asset)} />
                                    </Table.Cell>
                                </Table.Row>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>
        </AppLayout>
    );
}
