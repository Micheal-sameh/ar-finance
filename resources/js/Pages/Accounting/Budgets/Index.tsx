import { Head, Link, router, usePage } from '@inertiajs/react';
import { BarChart3, PiggyBank, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { useConfirm } from '@/Components/ui/ConfirmProvider';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Modal } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import type { BudgetOverviewRow } from '@/types/finance';
import { formatDate } from '@/utils/finance';

interface Props {
    budgets: BudgetOverviewRow[];
    currentYear: number;
}

export default function BudgetsIndex({ budgets, currentYear }: Props) {
    const confirm = useConfirm();
    const { auth } = usePage<{ auth: { permissions: string[] } }>().props;
    const canManage = auth.permissions.includes('accounts.manage');
    const existing = new Set(budgets.map((budget) => budget.year));
    const choices = [currentYear - 1, currentYear, currentYear + 1, currentYear + 2].filter((year) => !existing.has(year));
    const [open, setOpen] = useState(false);
    const [year, setYear] = useState<number>(choices[0] ?? currentYear + 3);

    async function remove(row: BudgetOverviewRow) {
        if (await confirm(`Delete the ${row.year} budget? Your actual figures are not affected.`, { variant: 'danger', confirmLabel: 'Delete budget' })) {
            router.delete(route('budgets.destroy', row.year));
        }
    }

    return (
        <>
            <Head title="Budgets" />

            <PageHeader
                title="Budgets"
                subtitle="Plan revenue and spending for each year, then see how you are doing against it."
                action={
                    canManage && (
                        <Button leadingIcon={<Plus size={16} />} onClick={() => setOpen(true)}>
                            New budget
                        </Button>
                    )
                }
            />

            <Card padded={false}>
                {budgets.length === 0 ? (
                    <EmptyState
                        icon={<PiggyBank size={20} />}
                        title="No budgets yet"
                        description="A budget is a monthly amount for each revenue and expense account. Start with a year — you can copy last year's actuals to save time."
                        action={canManage && <Button onClick={() => setOpen(true)}>Create your first budget</Button>}
                    />
                ) : (
                    <div className="table-responsive">
                        <table className="table align-middle mb-0" style={{ fontSize: '14px' }}>
                            <thead>
                                <tr style={{ color: 'var(--af-label)', fontSize: '12px', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                                    <th className="ps-3">Year</th>
                                    <th className="text-end">Planned revenue</th>
                                    <th className="text-end">Planned expenses</th>
                                    <th className="text-end">Planned profit</th>
                                    <th className="text-end">Accounts</th>
                                    <th>Last changed</th>
                                    <th className="pe-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {budgets.map((row) => (
                                    <tr key={row.year}>
                                        <td className="ps-3">
                                            <Link href={route('budgets.edit', row.year)} style={{ fontWeight: 600 }}>
                                                {row.year}
                                            </Link>
                                        </td>
                                        <td className="text-end">
                                            <MoneyDisplay amount={row.revenue} />
                                        </td>
                                        <td className="text-end">
                                            <MoneyDisplay amount={row.expenses} />
                                        </td>
                                        <td className="text-end" style={{ color: row.net_profit < 0 ? 'var(--af-danger)' : 'var(--af-success)', fontWeight: 600 }}>
                                            <MoneyDisplay amount={row.net_profit} />
                                        </td>
                                        <td className="text-end">{row.lines}</td>
                                        <td>{formatDate(row.updated_at)}</td>
                                        <td className="pe-3 text-end">
                                            <div className="d-flex justify-content-end gap-2">
                                                <Link href={route('reports.budget-vs-actual', { from: `${row.year}-01-01`, to: `${row.year}-12-31` })}>
                                                    <Button size="sm" variant="outline" leadingIcon={<BarChart3 size={14} />}>
                                                        Compare
                                                    </Button>
                                                </Link>
                                                <Link href={route('budgets.edit', row.year)}>
                                                    <Button size="sm" variant="outline">
                                                        {canManage ? 'Edit' : 'View'}
                                                    </Button>
                                                </Link>
                                                {canManage && (
                                                    <Button size="sm" variant="ghost" aria-label={`Delete ${row.year} budget`} onClick={() => remove(row)}>
                                                        <Trash2 size={14} />
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            <Modal
                open={open}
                onClose={() => setOpen(false)}
                title="New budget"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={() => router.get(route('budgets.edit', year))}>Continue</Button>
                    </>
                }
            >
                <Select label="Year" help="The calendar year this budget covers." value={year} onChange={(e) => setYear(Number(e.target.value))}>
                    {(choices.length > 0 ? choices : [currentYear + 3]).map((choice) => (
                        <option key={choice} value={choice}>
                            {choice}
                        </option>
                    ))}
                </Select>
            </Modal>
        </>
    );
}
