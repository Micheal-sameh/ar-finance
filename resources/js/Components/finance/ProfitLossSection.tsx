import { AmountLink } from './AmountLink';
import { MoneyDisplay } from './MoneyDisplay';

export interface ProfitLossSectionRow {
    key: string | number;
    label: string;
    amount: number;
    secondaryAmount?: number;
    /** Real account id to drill into via the General Ledger — omit for a synthetic row with no ledger behind it. */
    accountId?: number;
}

export interface ProfitLossSectionProps {
    title: string;
    rows: ProfitLossSectionRow[];
    totalLabel: string;
    total: number;
    secondaryTotal?: number;
    currency?: string;
    /** Header for the comparison column — omit to render a single-column section. */
    secondaryColumnLabel?: string;
    emptyLabel?: string;
    /** Date range each row's `amount` was computed over, for the General Ledger drill-down link. */
    linkFrom?: string | null;
    linkTo?: string | null;
    /** Date range each row's `secondaryAmount` was computed over. */
    secondaryLinkFrom?: string | null;
    secondaryLinkTo?: string | null;
}

/**
 * A labeled line-item group + total row. Reused for P&L's Revenue and
 * Expenses sections and Balance Sheet's Assets/Liabilities/Equity —
 * structurally identical, just different data and labels.
 */
export function ProfitLossSection({
    title,
    rows,
    totalLabel,
    total,
    secondaryTotal,
    currency = 'EGP',
    secondaryColumnLabel,
    emptyLabel = 'No activity in this period.',
    linkFrom,
    linkTo,
    secondaryLinkFrom,
    secondaryLinkTo,
}: ProfitLossSectionProps) {
    const showSecondary = secondaryColumnLabel !== undefined;

    return (
        <div className="mb-4">
            <div style={{ fontSize: '13px', fontWeight: 600, color: 'var(--af-navy)', marginBottom: '8px' }}>{title}</div>

            {showSecondary && (
                <div className="d-flex" style={{ fontSize: '11px', color: 'var(--af-label)', textTransform: 'uppercase' }}>
                    <div style={{ flex: 1 }} />
                    <div style={{ width: '140px' }} className="text-end">This period</div>
                    <div style={{ width: '140px' }} className="text-end">{secondaryColumnLabel}</div>
                </div>
            )}

            {rows.length === 0 ? (
                <div style={{ fontSize: '13px', color: 'var(--af-label)', padding: '6px 0' }}>{emptyLabel}</div>
            ) : (
                rows.map((row) => (
                    <div key={row.key} className="d-flex" style={{ fontSize: '14px', padding: '4px 0' }}>
                        <div style={{ flex: 1 }}>{row.label}</div>
                        <div style={{ width: showSecondary ? '140px' : '160px' }} className="text-end">
                            {row.accountId ? (
                                <AmountLink accountId={row.accountId} from={linkFrom} to={linkTo} amount={row.amount} currency={currency} />
                            ) : (
                                <MoneyDisplay amount={row.amount} currency={currency} />
                            )}
                        </div>
                        {showSecondary && (
                            <div style={{ width: '140px' }} className="text-end">
                                {row.accountId ? (
                                    <AmountLink
                                        accountId={row.accountId}
                                        from={secondaryLinkFrom}
                                        to={secondaryLinkTo}
                                        amount={row.secondaryAmount ?? 0}
                                        currency={currency}
                                    />
                                ) : (
                                    <MoneyDisplay amount={row.secondaryAmount ?? 0} currency={currency} />
                                )}
                            </div>
                        )}
                    </div>
                ))
            )}

            <div
                className="d-flex"
                style={{ fontSize: '14px', fontWeight: 600, padding: '8px 0', borderTop: '1px solid var(--af-border)', marginTop: '4px' }}
            >
                <div style={{ flex: 1 }}>{totalLabel}</div>
                <div style={{ width: showSecondary ? '140px' : '160px' }} className="text-end">
                    <MoneyDisplay amount={total} currency={currency} />
                </div>
                {showSecondary && (
                    <div style={{ width: '140px' }} className="text-end">
                        <MoneyDisplay amount={secondaryTotal ?? 0} currency={currency} />
                    </div>
                )}
            </div>
        </div>
    );
}
