export interface MoneyDisplayProps {
    amount: number | string;
    currency?: string;
    tone?: 'debit' | 'credit' | 'neutral';
    className?: string;
}

function formatAmount(amount: number | string): string {
    const value = typeof amount === 'string' ? parseFloat(amount) : amount;

    return value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const toneColor: Record<NonNullable<MoneyDisplayProps['tone']>, string> = {
    debit: 'var(--af-text)',
    credit: 'var(--af-text)',
    neutral: 'var(--af-text)',
};

/**
 * Consistent money formatting (2 decimals, thousands separator) + currency
 * suffix. `tone` lets callers dim a zero side of a debit/credit pair
 * without re-deriving the formatting logic per page.
 */
export function MoneyDisplay({ amount, currency = 'EGP', tone = 'neutral', className = '' }: MoneyDisplayProps) {
    const numeric = typeof amount === 'string' ? parseFloat(amount) : amount;
    const isZero = numeric === 0;

    return (
        <span
            className={className}
            style={{
                color: isZero ? 'var(--af-label)' : toneColor[tone],
                fontVariantNumeric: 'tabular-nums',
            }}
        >
            {formatAmount(amount)} {currency}
        </span>
    );
}
