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
                className={`table af-table align-middle mb-0 ${cards ? 'af-table-cards' : ''} ${className}`}
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
                    fontSize: '12.5px',
                    textTransform: 'uppercase',
                    letterSpacing: '0.04em',
                    backgroundColor: '#f6f7f4',
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
            style={{ borderBottom: '1px solid #e9ebe7', ...style }}
            {...rest}
        />
    );
}

function HeadCell({ className = '', style, ...rest }: ThHTMLAttributes<HTMLTableCellElement>) {
    return <th className={`fw-medium ${className}`} style={{ ...style }} {...rest} />;
}

function Cell({
    className = '',
    style,
    label,
    ...rest
}: TdHTMLAttributes<HTMLTableCellElement> & { label?: string }) {
    return <td className={className} style={{ ...style }} data-label={label} {...rest} />;
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
