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
import { Table } from '@/Components/ui/Table';
import { AppLayout } from '@/Layouts/AppLayout';
import type { Expense, Paginated } from '@/types/finance';
import { expenseStatusVariant, formatDate } from '@/utils/finance';

interface Props {
    expenses: Paginated<Expense>;
    filters: { status?: string; search?: string };
}

export default function ExpensesIndex({ expenses, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function runSearch(e: FormEvent) {
        e.preventDefault();
        router.get(route('expenses.index'), { ...filters, search }, { preserveState: true });
    }

    return (
        <AppLayout>
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
                    <form onSubmit={runSearch} className="d-flex gap-2" style={{ maxWidth: '320px' }}>
                        <Input placeholder="Search description…" value={search} onChange={(e) => setSearch(e.target.value)} />
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
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
        </AppLayout>
    );
}
