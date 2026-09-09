import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/Components/ui/Button';
import { AccountPicker } from './AccountPicker';

export interface JournalLineInput {
    account_id: number | null;
    debit: string;
    credit: string;
    description: string;
}

export function emptyJournalLine(): JournalLineInput {
    return { account_id: null, debit: '', credit: '', description: '' };
}

export interface JournalLineEditorProps {
    lines: JournalLineInput[];
    onChange: (lines: JournalLineInput[]) => void;
    errors?: Record<string, string>;
}

/**
 * The debit/credit line-item grid used by every journal-entry form
 * (manual entries now; invoice/expense/payroll posting previews later).
 * Debit and credit are mutually exclusive per row — typing in one clears
 * the other, matching how a real ledger line works.
 */
export function JournalLineEditor({ lines, onChange, errors = {} }: JournalLineEditorProps) {
    function updateLine(index: number, patch: Partial<JournalLineInput>) {
        onChange(lines.map((line, i) => (i === index ? { ...line, ...patch } : line)));
    }

    function addLine() {
        onChange([...lines, emptyJournalLine()]);
    }

    function removeLine(index: number) {
        onChange(lines.filter((_, i) => i !== index));
    }

    return (
        <div>
            <div className="d-flex fw-medium mb-2" style={{ fontSize: '12px', color: 'var(--af-label)', textTransform: 'uppercase' }}>
                <div style={{ flex: '2 1 0' }}>Account</div>
                <div style={{ flex: '1 1 0' }} className="text-end">Debit</div>
                <div style={{ flex: '1 1 0' }} className="text-end">Credit</div>
                <div style={{ flex: '1.5 1 0' }} className="ps-2">Description</div>
                <div style={{ width: '36px' }} />
            </div>

            {lines.map((line, index) => (
                <div key={index} className="d-flex align-items-start gap-2 mb-2">
                    <div style={{ flex: '2 1 0' }}>
                        <AccountPicker
                            value={line.account_id}
                            onChange={(accountId) => updateLine(index, { account_id: accountId })}
                            error={errors[`lines.${index}.account_id`]}
                        />
                    </div>
                    <div style={{ flex: '1 1 0' }}>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            className="form-control text-end"
                            style={{ borderRadius: 'var(--af-radius-sm)', fontSize: '14px' }}
                            value={line.debit}
                            onChange={(e) => updateLine(index, { debit: e.target.value, credit: e.target.value ? '' : line.credit })}
                            placeholder="0.00"
                        />
                    </div>
                    <div style={{ flex: '1 1 0' }}>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            className="form-control text-end"
                            style={{ borderRadius: 'var(--af-radius-sm)', fontSize: '14px' }}
                            value={line.credit}
                            onChange={(e) => updateLine(index, { credit: e.target.value, debit: e.target.value ? '' : line.debit })}
                            placeholder="0.00"
                        />
                    </div>
                    <div style={{ flex: '1.5 1 0' }}>
                        <input
                            type="text"
                            className="form-control"
                            style={{ borderRadius: 'var(--af-radius-sm)', fontSize: '14px' }}
                            value={line.description}
                            onChange={(e) => updateLine(index, { description: e.target.value })}
                            placeholder="Line memo (optional)"
                        />
                    </div>
                    <div style={{ width: '36px' }} className="pt-1">
                        <button
                            type="button"
                            className="btn btn-sm p-1"
                            style={{ color: 'var(--af-danger)' }}
                            onClick={() => removeLine(index)}
                            disabled={lines.length <= 2}
                            aria-label="Remove line"
                        >
                            <Trash2 size={16} />
                        </button>
                    </div>
                </div>
            ))}

            <Button type="button" variant="outline" size="sm" leadingIcon={<Plus size={14} />} onClick={addLine}>
                Add line
            </Button>
        </div>
    );
}
