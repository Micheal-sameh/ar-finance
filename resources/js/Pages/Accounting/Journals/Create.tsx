import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { emptyJournalLine, JournalLineEditor, type JournalLineInput } from '@/Components/finance/JournalLineEditor';
import { Icon } from '@/Components/layout/Icon';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Input } from '@/Components/ui/Input';

function formatMoney(value: number): string {
    return `EGP ${value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

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
    const difference = Math.abs(totalDebit - totalCredit);
    const isBalanced = difference < 0.005 && totalDebit > 0;
    const isOutOfBalance = difference >= 0.005;

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            lines: data.lines.map((line) => ({
                ...line,
                debit: line.debit || '0',
                credit: line.credit || '0',
            })),
        }));
        form.post(route('journals.store'));
    }

    return (
        <>
            <Head title="New Journal Entry" />

            <PageHeader title="New journal entry" subtitle="Record a manual debit and credit entry. Manual entries post directly to the general ledger." />

            <form onSubmit={submit}>
                <div className="journal-layout">
                    <div>
                        <article className="card form-card">
                            <div className="form-grid">
                                <Input
                                    type="date"
                                    label="Posting date"
                                    value={form.data.date}
                                    onChange={(e) => form.setData('date', e.target.value)}
                                    error={form.errors.date}
                                />
                                <Input
                                    label="Reference"
                                    value={form.data.reference}
                                    onChange={(e) => form.setData('reference', e.target.value)}
                                    error={form.errors.reference}
                                    placeholder="Optional"
                                />
                                <div className="wide">
                                    <Input
                                        label="Description"
                                        value={form.data.description}
                                        onChange={(e) => form.setData('description', e.target.value)}
                                        error={form.errors.description}
                                        placeholder="e.g. Owner capital contribution"
                                    />
                                </div>
                            </div>
                        </article>

                        <article className="card journal-lines">
                            <div className="card-head">
                                <div>
                                    <h2>Entry lines</h2>
                                    <p>Debits and credits must be equal before posting</p>
                                </div>
                            </div>
                            <JournalLineEditor
                                lines={form.data.lines}
                                onChange={(lines) => form.setData('lines', lines)}
                                errors={form.errors as Record<string, string>}
                            />
                            {form.errors.lines && <div className="field-error" style={{ padding: '0 15px 10px' }}>{form.errors.lines}</div>}
                            <div className="journal-total">
                                <span>Totals</span>
                                <b>{formatMoney(totalDebit)}</b>
                                <b>{formatMoney(totalCredit)}</b>
                            </div>
                        </article>
                    </div>

                    <aside>
                        <div className={`balance-indicator ${isOutOfBalance ? 'unbalanced' : ''}`}>
                            <div className="check-circle">
                                <Icon name={isOutOfBalance ? 'close' : 'check'} />
                            </div>
                            <h2>{isOutOfBalance ? 'Entry is out of balance' : isBalanced ? 'Entry is balanced' : 'Enter debits and credits'}</h2>
                            <p>{isOutOfBalance ? 'Total debits must equal total credits' : 'Total debits equal total credits'}</p>
                            <div>
                                <span>Debit</span>
                                <b>{formatMoney(totalDebit)}</b>
                            </div>
                            <div>
                                <span>Credit</span>
                                <b>{formatMoney(totalCredit)}</b>
                            </div>
                            <div className="difference">
                                <span>Difference</span>
                                <b>{formatMoney(difference)}</b>
                            </div>
                        </div>
                        <button type="submit" className="button primary full" disabled={!isBalanced || form.processing}>
                            {form.processing ? 'Posting...' : 'Post journal entry'}
                        </button>
                    </aside>
                </div>
            </form>
        </>
    );
}
