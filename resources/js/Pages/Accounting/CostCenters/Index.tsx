import { Head, router, useForm } from '@inertiajs/react';
import { PiggyBank, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { BudgetBar } from '@/Components/finance/BudgetBar';
import { CostCenterPicker } from '@/Components/finance/CostCenterPicker';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { useConfirm } from '@/Components/ui/ConfirmProvider';
import { EmptyState } from '@/Components/ui/EmptyState';
import { ExportButton } from '@/Components/ui/ExportButton';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { Table } from '@/Components/ui/Table';
import type { CostCenter, CostCenterSummaryRow, CostCenterType, Paginated } from '@/types/finance';

interface Props {
    costCenters: Paginated<CostCenter>;
    summary: CostCenterSummaryRow[];
    filters: { type?: string; search?: string; from: string; to: string };
}

export default function CostCentersIndex({ costCenters, summary, filters }: Props) {
    const confirm = useConfirm();
    const [search, setSearch] = useState(filters.search ?? '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<CostCenter | null>(null);

    const form = useForm<{
        name: string;
        type: CostCenterType;
        budget: string;
        parent_id: number | null;
    }>({
        name: '',
        type: 'cost',
        budget: '',
        parent_id: null,
    });

    function openCreate() {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setModalOpen(true);
    }

    function openEdit(costCenter: CostCenter) {
        setEditing(costCenter);
        form.setData({
            name: costCenter.name,
            type: costCenter.type,
            budget: costCenter.budget ?? '',
            parent_id: costCenter.parent_id,
        });
        form.clearErrors();
        setModalOpen(true);
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        if (editing) {
            form.put(route('cost-centers.update', editing.id), { onSuccess: () => setModalOpen(false) });
        } else {
            form.post(route('cost-centers.store'), {
                onSuccess: () => {
                    setModalOpen(false);
                    form.reset();
                },
            });
        }
    }

    async function destroy(costCenter: CostCenter) {
        if (await confirm(`Delete center "${costCenter.name}"?`, { variant: 'danger', confirmLabel: 'Delete' })) {
            router.delete(route('cost-centers.destroy', costCenter.id));
        }
    }

    function runSearch(e: FormEvent) {
        e.preventDefault();
        router.get(route('cost-centers.index'), { ...filters, search }, { preserveState: true });
    }

    function runFilters(next: Partial<Props['filters']>) {
        router.get(route('cost-centers.index'), { ...filters, search, ...next }, { preserveState: true });
    }

    function clearFilters() {
        setSearch('');
        router.get(route('cost-centers.index'), {}, { preserveState: true });
    }

    const summaryByCenter = new Map(summary.map((row) => [row.cost_center_id, row]));

    return (
        <>
            <Head title="P&C Centers" />

            <PageHeader
                title="P&C Centers"
                subtitle={`Budget vs actual, ${filters.from} to ${filters.to}.`}
                action={
                    <div className="d-flex gap-2">
                        <ExportButton href={route('cost-centers.export', filters)} />
                        <Button leadingIcon={<Plus size={16} />} onClick={openCreate}>
                            New Center
                        </Button>
                    </div>
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <form onSubmit={runSearch} className="af-filter-bar">
                        <Input
                            placeholder="Search by name…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            style={{ maxWidth: '240px' }}
                        />
                        <Select
                            value={filters.type ?? ''}
                            onChange={(e) => runFilters({ type: e.target.value || undefined })}
                            style={{ maxWidth: '170px' }}
                        >
                            <option value="">All types</option>
                            <option value="cost">Cost Center</option>
                            <option value="profit">Profit Center</option>
                        </Select>
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                        {(filters.type || filters.search) && (
                            <Button type="button" variant="ghost" onClick={clearFilters}>
                                Clear
                            </Button>
                        )}
                    </form>
                </div>

                {costCenters.data.length === 0 ? (
                    <EmptyState
                        icon={<PiggyBank size={20} />}
                        title="No centers yet"
                        description="Create a cost or profit center to start tagging expenses and journal lines."
                        action={<Button onClick={openCreate}>New Center</Button>}
                    />
                ) : (
                    <Table cards>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Name</Table.HeadCell>
                            <Table.HeadCell>Type</Table.HeadCell>
                            <Table.HeadCell style={{ width: '260px' }}>Budget vs Actual</Table.HeadCell>
                            <Table.HeadCell className="text-end">Net</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Actions</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {costCenters.data.map((costCenter) => {
                                const row = summaryByCenter.get(costCenter.id);

                                return (
                                    <Table.Row key={costCenter.id}>
                                        <Table.Cell className="ps-3" label="Name">
                                            {costCenter.name}
                                            {costCenter.parent && (
                                                <span style={{ color: 'var(--af-label)', fontSize: '12px' }}>
                                                    {' '}
                                                    &middot; under {costCenter.parent.name}
                                                </span>
                                            )}
                                        </Table.Cell>
                                        <Table.Cell label="Type">
                                            <Badge variant={costCenter.type === 'profit' ? 'info' : 'neutral'}>{costCenter.type}</Badge>
                                        </Table.Cell>
                                        <Table.Cell label="Budget vs Actual">
                                            <BudgetBar budget={row?.budget ?? null} spent={row?.spent ?? 0} />
                                        </Table.Cell>
                                        <Table.Cell
                                            className="text-end"
                                            label="Net"
                                            style={{ color: (row?.net ?? 0) < 0 ? 'var(--af-danger)' : 'var(--af-success)' }}
                                        >
                                            {(row?.net ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                        </Table.Cell>
                                        <Table.Cell className="text-end pe-3" label="Actions">
                                            <div className="d-flex justify-content-end gap-1">
                                                <button type="button" className="btn btn-sm p-1" style={{ color: 'var(--af-label)' }} onClick={() => openEdit(costCenter)} aria-label="Edit">
                                                    <Pencil size={15} />
                                                </button>
                                                <button type="button" className="btn btn-sm p-1" style={{ color: 'var(--af-danger)' }} onClick={() => destroy(costCenter)} aria-label="Delete">
                                                    <Trash2 size={15} />
                                                </button>
                                            </div>
                                        </Table.Cell>
                                    </Table.Row>
                                );
                            })}
                        </tbody>
                    </Table>
                )}
            </Card>

            <Modal
                open={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editing ? 'Edit Center' : 'New Center'}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={submit} loading={form.processing}>
                            {editing ? 'Save changes' : 'Create center'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="d-flex flex-column gap-3">
                    <Input label="Name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
                    <Select
                        label="Type"
                        value={form.data.type}
                        onChange={(e) => form.setData('type', e.target.value as CostCenterType)}
                        error={form.errors.type}
                    >
                        <option value="cost">Cost Center</option>
                        <option value="profit">Profit Center</option>
                    </Select>
                    <Input
                        type="number"
                        step="0.01"
                        min="0"
                        label="Budget (optional)"
                        value={form.data.budget}
                        onChange={(e) => form.setData('budget', e.target.value)}
                        error={form.errors.budget}
                        placeholder="Leave blank for no budget"
                    />
                    <div>
                        <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                            Parent (optional)
                        </label>
                        <CostCenterPicker value={form.data.parent_id} onChange={(id) => form.setData('parent_id', id)} error={form.errors.parent_id} />
                    </div>
                </form>
            </Modal>
        </>
    );
}
