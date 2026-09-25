import type { HTMLAttributes, TdHTMLAttributes, ThHTMLAttributes } from 'react';

function TableRoot({
    className = '',
    children,
    cards = false,
    ...rest
}: HTMLAttributes<HTMLTableElement> & { cards?: boolean }) {
    return (
        <div style={{ overflowX: 'auto' }}>
            <table
                className={`table align-middle mb-0 ${cards ? 'af-table-cards' : ''} ${className}`}
                style={{ fontSize: '14px', color: 'var(--af-text)' }}
                {...rest}
            >
                {children}
            </table>
        </div>
    );
}

function Head({ children, ...rest }: HTMLAttributes<HTMLTableSectionElement>) {
    return (
        <thead {...rest}>
            <tr
                style={{
                    color: 'var(--af-label)',
                    fontSize: '12px',
                    textTransform: 'uppercase',
                    letterSpacing: '0.03em',
                    borderBottom: '1px solid var(--af-border)',
                }}
            >
                {children}
            </tr>
        </thead>
    );
}

function Row({ className = '', style, ...rest }: HTMLAttributes<HTMLTableRowElement>) {
    return (
        <tr
            className={className}
            style={{ borderBottom: '1px solid var(--af-border)', ...style }}
            {...rest}
        />
    );
}

function HeadCell({ className = '', style, ...rest }: ThHTMLAttributes<HTMLTableCellElement>) {
    return <th className={`fw-medium py-2 ${className}`} style={{ ...style }} {...rest} />;
}

function Cell({
    className = '',
    style,
    label,
    ...rest
}: TdHTMLAttributes<HTMLTableCellElement> & { label?: string }) {
    return <td className={`py-2 ${className}`} style={{ ...style }} data-label={label} {...rest} />;
}

/**
 * Compound table primitive: <Table><Table.Head>...<Table.Row><Table.Cell>.
 * Every list view in the app should use this instead of raw <table> markup.
 *
 * Pass `cards` to make rows collapse into stacked cards below the `md`
 * breakpoint (see .af-table-cards in app.css) — give each `Table.Cell` a
 * `label` matching its column header so the card can show it inline.
 */
export const Table = Object.assign(TableRoot, {
    Head,
    HeadCell,
    Row,
    Cell,
});
