import { Head, Link, router } from '@inertiajs/react';
import { BookOpen, Plus } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Input } from '@/Components/ui/Input';
import { Table } from '@/Components/ui/Table';
import { AppLayout } from '@/Layouts/AppLayout';
import type { JournalEntry, Paginated } from '@/types/finance';

interface Props {
    entries: Paginated<JournalEntry>;
    filters: { source_type?: string; from?: string; to?: string; search?: string };
}

function entryTotal(entry: JournalEntry): number {
    return entry.lines.reduce((sum, line) => sum + parseFloat(line.debit), 0);
}

export default function JournalsIndex({ entries, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function runSearch(e: FormEvent) {
        e.preventDefault();
        router.get(route('journals.index'), { ...filters, search }, { preserveState: true });
    }

    return (
        <AppLayout>
            <Head title="Journal Entries" />

            <PageHeader
                title="Journal Entries"
                subtitle="Every posted transaction in the general ledger."
                action={
                    <Link href={route('journals.create')}>
                        <Button leadingIcon={<Plus size={16} />}>New Entry</Button>
                    </Link>
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <form onSubmit={runSearch} className="d-flex gap-2" style={{ maxWidth: '320px' }}>
                        <Input
                            placeholder="Search description or reference…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
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
                                    <Table.Cell className="ps-3">{entry.date}</Table.Cell>
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
        </AppLayout>
    );
}
