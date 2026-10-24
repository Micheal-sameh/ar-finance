import { Icon } from '@/Components/layout/Icon';
import { AccountPicker } from './AccountPicker';
import { useAccounts } from '@/hooks/useAccounts';
import type { AccountType, CostCenterType } from '@/types/finance';
import { CostCenterPicker } from './CostCenterPicker';

export interface JournalLineInput {
    account_id: number | null;
    debit: string;
    credit: string;
    cost_center_id: number | null;
    description: string;
}

export function emptyJournalLine(): JournalLineInput {
    return { account_id: null, debit: '', credit: '', cost_center_id: null, description: '' };
}

const CENTER_TYPE_BY_ACCOUNT_TYPE: Partial<Record<AccountType, CostCenterType>> = { revenue: 'profit', expense: 'cost' };

function hasAmount(line: JournalLineInput): boolean {
    return (parseFloat(line.debit) || 0) > 0 || (parseFloat(line.credit) || 0) > 0;
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
    const { accounts } = useAccounts();
    const requiredCenterType = (accountId: number | null): CostCenterType | null =>
        CENTER_TYPE_BY_ACCOUNT_TYPE[accounts.find((a) => a.id === accountId)?.type as AccountType] ?? null;

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
        <>
            <div className='journal-head cc'>
                <span>ACCOUNT</span>
                <span>DESCRIPTION</span>
                <span className='text-end'>DEBIT</span>
                <span className='text-end'>CREDIT</span>
                <span>PROFIT / COST CENTER *</span>
                <span />
            </div>

            {lines.map((line, index) => (
                <div key={index} className='journal-row cc'>
                    <div>
                        <AccountPicker
                            value={line.account_id}
                            onChange={(accountId) => updateLine(index, { account_id: accountId, cost_center_id: null })}
                            error={errors[`lines.${index}.account_id`]}
                        />
                    </div>
                    <div>
                        <input
                            type='text'
                            value={line.description}
                            onChange={(e) => updateLine(index, { description: e.target.value })}
                            placeholder='Line memo (optional)'
                            aria-label='Description'
                        />
                    </div>
                    <div>
                        <input
                            type='number'
                            step='0.01'
                            min='0'
                            className='num'
                            value={line.debit}
                            onChange={(e) =>
                                updateLine(index, {
                                    debit: e.target.value,
                                    credit: e.target.value ? '' : line.credit,
                                    cost_center_id: e.target.value || line.credit ? line.cost_center_id : null,
                                })
                            }
                            placeholder='Debit'
                            aria-label='Debit'
                        />
                    </div>
                    <div>
                        <input
                            type='number'
                            step='0.01'
                            min='0'
                            className='num'
                            value={line.credit}
                            onChange={(e) =>
                                updateLine(index, {
                                    credit: e.target.value,
                                    debit: e.target.value ? '' : line.debit,
                                    cost_center_id: e.target.value || line.debit ? line.cost_center_id : null,
                                })
                            }
                            placeholder='Credit'
                            aria-label='Credit'
                        />
                    </div>
                    <div>
                        <CostCenterPicker
                            value={line.cost_center_id}
                            filterType={requiredCenterType(line.account_id)}
                            onChange={(costCenterId) => updateLine(index, { cost_center_id: costCenterId })}
                            error={errors[`lines.${index}.cost_center_id`]}
                            disabled={!hasAmount(line)}
                        />
                    </div>
                    <button
                        type='button'
                        className='icon-btn'
                        onClick={() => removeLine(index)}
                        disabled={lines.length <= 2}
                        aria-label='Remove line'
                    >
                        <Icon name='close' size={15} />
                    </button>
                </div>
            ))}

            <button type='button' className='add-line' onClick={addLine}>
                <Icon name='plus' size={16} />
                Add another line
            </button>
        </>
    );
}
