import { Link } from '@inertiajs/react';
import { MoneyDisplay, type MoneyDisplayProps } from './MoneyDisplay';

export interface AmountLinkProps extends MoneyDisplayProps {
    accountId: number;
    from?: string | null;
    to?: string | null;
    costCenterId?: number | string | null;
}

/**
 * Wraps MoneyDisplay in a link to that account's General Ledger, scoped to
 * the same date range (and cost center, if the figure was narrowed to one)
 * that produced the figure — every amount on a report should be traceable
 * back to the posted lines behind it. account_id 0 marks a synthetic row
 * (e.g. a derived retained-earnings line) with no real ledger to show, so
 * those render as plain MoneyDisplay instead of a link.
 */
export function AmountLink({ accountId, from, to, costCenterId, ...moneyProps }: AmountLinkProps) {
    if (!accountId) {
        return <MoneyDisplay {...moneyProps} />;
    }

    return (
        <Link
            href={route('reports.general-ledger', {
                account_id: accountId,
                from: from ?? null,
                to: to ?? null,
                cost_center_id: costCenterId ?? null,
            })}
            className="af-amount-link"
        >
            <MoneyDisplay {...moneyProps} />
        </Link>
    );
}
