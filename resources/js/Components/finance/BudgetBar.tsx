import { MoneyDisplay } from './MoneyDisplay';

export interface BudgetBarProps {
    budget: number | null;
    spent: number;
    currency?: string;
}

/**
 * Progress bar with an over-budget red state. Reused across Cost Centers
 * now, and Fixed Assets/Payroll later wherever a budget-vs-actual figure
 * needs the same treatment.
 */
export function BudgetBar({ budget, spent, currency = 'EGP' }: BudgetBarProps) {
    if (budget === null) {
        return (
            <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>
                No budget set — <MoneyDisplay amount={spent} currency={currency} /> spent
            </div>
        );
    }

    const percent = budget > 0 ? Math.min(100, (spent / budget) * 100) : 0;
    const overBudget = spent > budget;

    return (
        <div>
            <div className="d-flex justify-content-between mb-1" style={{ fontSize: '12px' }}>
                <span style={{ color: overBudget ? 'var(--af-danger)' : 'var(--af-text)' }}>
                    <MoneyDisplay amount={spent} currency={currency} /> of <MoneyDisplay amount={budget} currency={currency} />
                </span>
                <span style={{ color: overBudget ? 'var(--af-danger)' : 'var(--af-label)' }}>
                    {Math.round((spent / budget) * 100)}%
                </span>
            </div>
            <div
                style={{
                    height: '6px',
                    borderRadius: '999px',
                    backgroundColor: '#EEF1F6',
                    overflow: 'hidden',
                }}
            >
                <div
                    style={{
                        height: '100%',
                        width: `${percent}%`,
                        backgroundColor: overBudget ? 'var(--af-danger)' : 'var(--af-primary)',
                        borderRadius: '999px',
                        transition: 'width 0.2s ease',
                    }}
                />
            </div>
        </div>
    );
}
