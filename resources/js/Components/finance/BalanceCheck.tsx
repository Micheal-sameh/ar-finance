import { Check, X } from 'lucide-react';
import { MoneyDisplay } from './MoneyDisplay';

export interface BalanceCheckProps {
    totalDebit: number;
    totalCredit: number;
    currency?: string;
}

/**
 * The ✓/✗ balanced indicator. Reused in Trial Balance, Balance Sheet, and
 * live journal-entry validation — one place that decides what "balanced"
 * means (within a cent, to absorb float rounding).
 */
export function BalanceCheck({ totalDebit, totalCredit, currency = 'EGP' }: BalanceCheckProps) {
    const isBalanced = Math.abs(totalDebit - totalCredit) < 0.005;

    return (
        <div
            className="d-flex align-items-center gap-2"
            style={{
                padding: '8px 14px',
                borderRadius: 'var(--af-radius-sm)',
                backgroundColor: isBalanced ? '#EAF7EE' : '#FCEBEB',
                border: `1px solid ${isBalanced ? '#BFE6CB' : '#F4BFBF'}`,
                color: isBalanced ? 'var(--af-success)' : 'var(--af-danger)',
                fontSize: '13px',
                fontWeight: 500,
            }}
        >
            {isBalanced ? <Check size={16} /> : <X size={16} />}
            <span>{isBalanced ? 'Balanced' : 'Out of balance'}</span>
            {!isBalanced && (
                <span style={{ color: 'var(--af-label)', fontWeight: 400 }}>
                    (diff <MoneyDisplay amount={Math.abs(totalDebit - totalCredit)} currency={currency} />)
                </span>
            )}
        </div>
    );
}
