import { Head, Link, router } from '@inertiajs/react';
import { BookOpen, Plus } from 'lucide-react';
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
import type { JournalEntry, Paginated } from '@/types/finance';
import { formatDate } from '@/utils/finance';

interface AuthorOption {
    id: number;
    name: string;
}

interface Props {
    entries: Paginated<JournalEntry>;
    filters: { source_type?: string; created_by?: string; from?: string; to?: string; search?: string };
    authorOptions: AuthorOption[];
}

const SOURCE_TYPES = [
    { value: 'invoice', label: 'Invoice' },
    { value: 'expense', label: 'Expense' },
    { value: 'payroll', label: 'Payroll' },
    { value: 'manual', label: 'Manual' },
    { value: 'depreciation', label: 'Depreciation' },
    { value: 'revaluation', label: 'Currency Revaluation' },
    { value: 'opening_balance', label: 'Opening Balance' },
];

function entryTotal(entry: JournalEntry): number {
    return entry.lines.reduce((sum, line) => sum + parseFloat(line.debit), 0);
}

export default function JournalsIndex({ entries, filters, authorOptions }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function runSearch(e: FormEvent) {
        e.preventDefault();
        router.get(route('journals.index'), { ...filters, search }, { preserveState: true });
    }

    function runFilters(next: Partial<Props['filters']>) {
        router.get(route('journals.index'), { ...filters, search, ...next }, { preserveState: true });
    }

    function clearFilters() {
        setSearch('');
        router.get(route('journals.index'), {}, { preserveState: true });
    }

    return (
        <>
            <Head title="Journal Entries" />

            <PageHeader
                title="Journal Entries"
                subtitle="Every posted transaction in the general ledger."
                action={
                    <div className="d-flex gap-2">
                        <ExportButton href={route('journals.export', filters)} />
                        <Link href={route('journals.create')}>
                            <Button leadingIcon={<Plus size={16} />}>New Entry</Button>
                        </Link>
                    </div>
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <form onSubmit={runSearch} className="d-flex gap-2 flex-wrap align-items-end">
                        <Input
                            placeholder="Search description or reference…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            style={{ maxWidth: '240px' }}
                        />
                        <Select
                            value={filters.source_type ?? ''}
                            onChange={(e) => runFilters({ source_type: e.target.value || undefined })}
                            style={{ maxWidth: '170px' }}
                        >
                            <option value="">All sources</option>
                            {SOURCE_TYPES.map((s) => (
                                <option key={s.value} value={s.value}>
                                    {s.label}
                                </option>
                            ))}
                        </Select>
                        <Select
                            value={filters.created_by ?? ''}
                            onChange={(e) => runFilters({ created_by: e.target.value || undefined })}
                            style={{ maxWidth: '170px' }}
                        >
                            <option value="">All authors</option>
                            {authorOptions.map((author) => (
                                <option key={author.id} value={author.id}>
                                    {author.name}
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
                        {(filters.source_type || filters.created_by || filters.from || filters.to || filters.search) && (
                            <Button type="button" variant="ghost" onClick={clearFilters}>
                                Clear
                            </Button>
                        )}
                    </form>
                </div>

                {entries.data.length === 0 ? (
                    <EmptyState
                        icon={<BookOpen size={20} />}
                        title="No journal entries yet"
                        description="Post your first manual journal entry to see it here."
                        action={
                            <Link href={route('journals.create')}>
                                <Button>New Entry</Button>
                            </Link>
                        }
                    />
                ) : (
                    <Table>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Date</Table.HeadCell>
                            <Table.HeadCell>Description</Table.HeadCell>
                            <Table.HeadCell>Reference</Table.HeadCell>
                            <Table.HeadCell>Source</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Amount</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {entries.data.map((entry) => (
                                <Table.Row key={entry.id} style={{ cursor: 'pointer' }} onClick={() => router.get(route('journals.show', entry.id))}>
                                    <Table.Cell className="ps-3">{formatDate(entry.date)}</Table.Cell>
                                    <Table.Cell>{entry.description}</Table.Cell>
                                    <Table.Cell>{entry.reference ?? '—'}</Table.Cell>
                                    <Table.Cell>
                                        <Badge variant="neutral">{entry.source_type}</Badge>
                                    </Table.Cell>
                                    <Table.Cell className="text-end pe-3">
                                        <MoneyDisplay amount={entryTotal(entry)} />
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
