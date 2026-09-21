import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { BalanceCheck } from '@/Components/finance/BalanceCheck';
import { emptyJournalLine, JournalLineEditor, type JournalLineInput } from '@/Components/finance/JournalLineEditor';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Input } from '@/Components/ui/Input';
import { AppLayout } from '@/Layouts/AppLayout';

export default function JournalsCreate() {
    const form = useForm<{
        date: string;
        description: string;
        reference: string;
        lines: JournalLineInput[];
    }>({
        date: new Date().toISOString().slice(0, 10),
        description: '',
        reference: '',
        lines: [emptyJournalLine(), emptyJournalLine()],
    });

    const totalDebit = form.data.lines.reduce((sum, line) => sum + (parseFloat(line.debit) || 0), 0);
    const totalCredit = form.data.lines.reduce((sum, line) => sum + (parseFloat(line.credit) || 0), 0);
    const isBalanced = Math.abs(totalDebit - totalCredit) < 0.005 && totalDebit > 0;

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            lines: data.lines.map((line) => ({
                ...line,
                debit: line.debit || '0',
                credit: line.credit || '0',
            })),
        })).post(route('journals.store'));
    }

    return (
        <AppLayout>
            <Head title="New Journal Entry" />

            <PageHeader title="New Journal Entry" subtitle="Manual entries post directly to the general ledger." />

            <form onSubmit={submit}>
                <Card>
                    <div className="row g-3 mb-4">
                        <div className="col-md-3">
                            <Input
                                type="date"
                                label="Date"
                                value={form.data.date}
                                onChange={(e) => form.setData('date', e.target.value)}
                                error={form.errors.date}
                            />
                        </div>
                        <div className="col-md-6">
                            <Input
                                label="Description"
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                                error={form.errors.description}
                                placeholder="e.g. Owner capital contribution"
                            />
                        </div>
                        <div className="col-md-3">
                            <Input
                                label="Reference (optional)"
                                value={form.data.reference}
                                onChange={(e) => form.setData('reference', e.target.value)}
                                error={form.errors.reference}
                            />
                        </div>
                    </div>

                    <JournalLineEditor
                        lines={form.data.lines}
                        onChange={(lines) => form.setData('lines', lines)}
                        errors={form.errors as Record<string, string>}
                    />
                    {form.errors.lines && (
                        <div style={{ color: 'var(--af-danger)', fontSize: '13px', marginTop: '8px' }}>
                            {form.errors.lines}
                        </div>
                    )}

                    <div className="d-flex align-items-center justify-content-between mt-4 pt-3" style={{ borderTop: '1px solid var(--af-border)' }}>
                        <BalanceCheck totalDebit={totalDebit} totalCredit={totalCredit} />
                        <Button type="submit" disabled={!isBalanced} loading={form.processing}>
                            Post Journal Entry
                        </Button>
                    </div>
                </Card>
            </form>
        </AppLayout>
    );
}
