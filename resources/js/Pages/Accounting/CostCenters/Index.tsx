import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Upload } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { CostCenterPicker } from '@/Components/finance/CostCenterPicker';
import { Icon } from '@/Components/layout/Icon';
import { PageHeader } from '@/Components/layout/PageHeader';
import { ServiceField } from '@/Components/layout/ServiceField';
import { Button } from '@/Components/ui/Button';
import { useConfirm } from '@/Components/ui/ConfirmProvider';
import { ExportButton } from '@/Components/ui/ExportButton';
import { FilterPanel } from '@/Components/ui/FilterPanel';
import { ImportModal } from '@/Components/ui/ImportModal';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import type { CostCenter, CostCenterSummaryRow, CostCenterType, Paginated } from '@/types/finance';
import { InfoTip } from '@/Components/ui/InfoTip';

interface Props {
    costCenters: Paginated<CostCenter>;
    summary: CostCenterSummaryRow[];
    filters: { type?: string; search?: string; from: string; to: string };
    viewingAllTenants: boolean;
}

function formatMoney(value: number): string {
    return `EGP ${value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function utilizationTone(percent: number): string {
    if (percent >= 90) {
        return 'red';
    }

    return percent >= 85 ? 'amber' : 'green';
}

export default function CostCentersIndex({ costCenters, summary, filters, viewingAllTenants }: Props) {
    const confirm = useConfirm();
    const [search, setSearch] = useState(filters.search ?? '');
    const [modalOpen, setModalOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const [editing, setEditing] = useState<CostCenter | null>(null);
    const [serviceId, setServiceId] = useState<number | null>(null);

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
        setServiceId(null);
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

        form.transform((data) => (editing ? data : { ...data, for_tenant: serviceId }));

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
    const totals = summary.reduce(
        (sum, row) => (row.budget === null ? sum : { budget: sum.budget + row.budget, spent: sum.spent + row.spent }),
        { budget: 0, spent: 0 },
    );

    return (
        <>
            <Head title="P&C Centers" />

            <PageHeader
                title="P&C Centers"
                subtitle={
                    viewingAllTenants
                        ? 'Every service’s cost/profit centers, combined.'
                        : `Budget vs actual, ${filters.from} to ${filters.to}.`
                }
                action={
                    (
                        <div className="d-flex gap-2">
                            {!viewingAllTenants && (
                                <Button variant="outline" leadingIcon={<Upload size={16} />} onClick={() => setImportOpen(true)}>
                                Import from Excel
                            </Button>
                            )}
                            {!viewingAllTenants && <ExportButton href={route('cost-centers.export', filters)} />}
                            <Button leadingIcon={<Plus size={16} />} onClick={openCreate}>
                                New Center
                            </Button>
                        </div>
                    )
                }
            />

            <div className="center-summary">
                <div>
                    <span>Total budget</span>
                    <b>{formatMoney(totals.budget)}</b>
                </div>
                <div>
                    <span>Actual spend</span>
                    <b>{formatMoney(totals.spent)}</b>
                </div>
                <div>
                    <span>Remaining</span>
                    <b>{formatMoney(totals.budget - totals.spent)}</b>
                </div>
                <div>
                    <span>Utilization</span>
                    <b>{totals.budget > 0 ? `${((totals.spent / totals.budget) * 100).toFixed(1)}%` : '—'}</b>
                </div>
            </div>

            <article className="card" style={{ marginBottom: '14px' }}>
                <div className="p-3">
                    <FilterPanel active={Boolean(filters.type || filters.search)}>
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
                    </FilterPanel>
                </div>
            </article>

            {costCenters.data.length === 0 ? (
                <article className="card empty-page">
                    <div className="empty-icon">
                        <Icon name="target" size={26} />
                    </div>
                    <h2>No centers yet</h2>
                    <p>Create a cost or profit center to start tagging expenses and journal lines.</p>
                    {(
                        <button type="button" className="button primary" onClick={openCreate}>
                            <Icon name="plus" size={16} />
                            New center
                        </button>
                    )}
                </article>
            ) : (
                <div className="center-grid">
                    {costCenters.data.map((costCenter) => {
                        const row = summaryByCenter.get(costCenter.id);
                        const budget = row?.budget ?? null;
                        const spent = row?.spent ?? 0;
                        const net = row?.net ?? 0;
                        const percent = budget !== null && budget > 0 ? (spent / budget) * 100 : null;

                        return (
                            <article className="card center-card" key={costCenter.id}>
                                <div className="center-title">
                                    <div className="center-icon">
                                        <Icon name="target" />
                                    </div>
                                    <div>
                                        <h2>{costCenter.name}</h2>
                                        <p>
                                            {costCenter.type === 'profit' ? 'Profit center' : 'Cost center'}
                                            {costCenter.parent && ` · under ${costCenter.parent.name}`}
                                            {viewingAllTenants && costCenter.tenant_name && ` · ${costCenter.tenant_name}`}
                                        </p>
                                    </div>
                                    {(
                                        <>
                                            <button type="button" className="icon-btn" onClick={() => openEdit(costCenter)} aria-label="Edit">
                                                <Pencil size={15} />
                                            </button>
                                            <button type="button" className="icon-btn" onClick={() => destroy(costCenter)} aria-label="Delete">
                                                <Trash2 size={15} />
                                            </button>
                                        </>
                                    )}
                                </div>
                                {percent === null ? (
                                    <div className="budget-head">
                                        <span>No budget set</span>
                                        <button type="button" className="btn btn-link p-0" style={{ fontSize: '12px' }} onClick={() => openEdit(costCenter)}>
                                            Set budget
                                        </button>
                                    </div>
                                ) : (
                                    <>
                                        <div className="budget-head">
                                            <span>Budget utilization</span>
                                            <b>{Math.round(percent)}%</b>
                                        </div>
                                        <div className="budget-bar">
                                            <i className={utilizationTone(percent)} style={{ width: `${Math.min(100, percent)}%` }} />
                                        </div>
                                    </>
                                )}
                                <div className="budget-values">
                                    <span>
                                        <small>SPENT</small>
                                        {formatMoney(spent)}
                                    </span>
                                    <span>
                                        <small>BUDGET</small>
                                        {budget === null ? '—' : formatMoney(budget)}
                                    </span>
                                </div>
                                <div className="center-profit">
                                    <span>Net P&amp;L</span>
                                    <b className={net < 0 ? 'negative' : ''}>{net < 0 ? `(${formatMoney(Math.abs(net))})` : formatMoney(net)}</b>
                                </div>
                            </article>
                        );
                    })}
                </div>
            )}

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
                    {viewingAllTenants && !editing && (
                        <ServiceField value={serviceId} onChange={setServiceId} error={(form.errors as Record<string, string>).for_tenant} />
                    )}
                    <Input label="Name" help="The department, project or location you want to track income and spending for, for example Main Hall or Marketing." value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
                    <Select
                        label="Type"
                        help="Whether this is a cost center (spending only) or a profit center (earns income too). It decides how it is grouped in reports."
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
                        help="The amount you plan to spend here. It is used in the budget-versus-actual report."
                        value={form.data.budget}
                        onChange={(e) => form.setData('budget', e.target.value)}
                        error={form.errors.budget}
                        placeholder="Leave blank for no budget"
                    />
                    <div>
                        <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                            Parent (optional) <InfoTip>Nest this under a bigger center so reports can roll the figures up.</InfoTip>
                        </label>
                        <CostCenterPicker tenantId={editing ? ((editing as { tenant_id?: number }).tenant_id ?? null) : serviceId} value={form.data.parent_id} onChange={(id) => form.setData('parent_id', id)} error={form.errors.parent_id} />
                    </div>
                </form>
            </Modal>

            <ImportModal
                open={importOpen}
                onClose={() => setImportOpen(false)}
                action={route('cost-centers.import')}
                templateHref={route('cost-centers.import-template')}
                requiredColumns={['name', 'type']}
                optionalColumns={['parent', 'budget', 'is_active']}
                notes={[
                    'type is cost or profit.',
                    'One row per center, with a header row.',
                    'Names must be unique. A parent is the name of an existing center or another row in the file, in any order.',
                ]}
            />
        </>
    );
}
