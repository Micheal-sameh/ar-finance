import { Head, Link, router } from '@inertiajs/react';
import { Plus, Receipt } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { ExportButton } from '@/Components/ui/ExportButton';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import { Table } from '@/Components/ui/Table';
import type { Expense, Paginated } from '@/types/finance';
import { expenseStatusVariant, formatDate } from '@/utils/finance';

interface Props {
    expenses: Paginated<Expense>;
    filters: { status?: string; from?: string; to?: string; search?: string };
}

const EXPENSE_STATUSES = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'paid', label: 'Paid' },
];

export default function ExpensesIndex({ expenses, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function runSearch(e: FormEvent) {
        e.preventDefault();
        router.get(route('expenses.index'), { ...filters, search }, { preserveState: true });
    }

    function runFilters(next: Partial<Props['filters']>) {
        router.get(route('expenses.index'), { ...filters, search, ...next }, { preserveState: true });
    }

    function clearFilters() {
        setSearch('');
        router.get(route('expenses.index'), {}, { preserveState: true });
    }

    return (
        <>
            <Head title="Expenses" />

            <PageHeader
                title="Expenses"
                subtitle="Spend recorded against the business."
                action={
                    <div className="d-flex gap-2">
                        <ExportButton href={route('expenses.export', filters)} />
                        <Link href={route('expenses.create')}>
                            <Button leadingIcon={<Plus size={16} />}>New Expense</Button>
                        </Link>
                    </div>
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <form onSubmit={runSearch} className="d-flex gap-2 flex-wrap align-items-end">
                        <Input
                            placeholder="Search description…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            style={{ maxWidth: '240px' }}
                        />
                        <Select
                            value={filters.status ?? ''}
                            onChange={(e) => runFilters({ status: e.target.value || undefined })}
                            style={{ maxWidth: '150px' }}
                        >
                            <option value="">All statuses</option>
                            {EXPENSE_STATUSES.map((s) => (
                                <option key={s.value} value={s.value}>
                                    {s.label}
                                </option>
                            ))}
                        </Select>
                        <Input
                            type="date"
                            label="From"
                            value={filters.from ?? ''}
                            onChange={(e) => runFilters({ from: e.target.value || undefined })}
                        />
                        <Input
                            type="date"
                            label="To"
                            value={filters.to ?? ''}
                            onChange={(e) => runFilters({ to: e.target.value || undefined })}
                        />
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                        {(filters.status || filters.from || filters.to || filters.search) && (
                            <Button type="button" variant="ghost" onClick={clearFilters}>
                                Clear
                            </Button>
                        )}
                    </form>
                </div>

                {expenses.data.length === 0 ? (
                    <EmptyState
                        icon={<Receipt size={20} />}
                        title="No expenses yet"
                        description="Record your first expense."
                        action={
                            <Link href={route('expenses.create')}>
                                <Button>New Expense</Button>
                            </Link>
                        }
                    />
                ) : (
                    <Table>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Date</Table.HeadCell>
                            <Table.HeadCell>Description</Table.HeadCell>
                            <Table.HeadCell>Category</Table.HeadCell>
                            <Table.HeadCell>Vendor</Table.HeadCell>
                            <Table.HeadCell>Status</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Amount</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {expenses.data.map((expense) => (
                                <Table.Row key={expense.id} style={{ cursor: 'pointer' }} onClick={() => router.get(route('expenses.show', expense.id))}>
                                    <Table.Cell className="ps-3">{formatDate(expense.date)}</Table.Cell>
                                    <Table.Cell>{expense.description}</Table.Cell>
                                    <Table.Cell>{expense.account?.name ?? '—'}</Table.Cell>
                                    <Table.Cell>{expense.vendor?.name ?? '—'}</Table.Cell>
                                    <Table.Cell>
                                        <Badge variant={expenseStatusVariant(expense.status)}>{expense.status}</Badge>
                                    </Table.Cell>
                                    <Table.Cell className="text-end pe-3">
                                        <MoneyDisplay amount={expense.amount} />
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
