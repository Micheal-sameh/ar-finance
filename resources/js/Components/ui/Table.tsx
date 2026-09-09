import type { HTMLAttributes, TdHTMLAttributes, ThHTMLAttributes } from 'react';

function TableRoot({ className = '', children, ...rest }: HTMLAttributes<HTMLTableElement>) {
    return (
        <div style={{ overflowX: 'auto' }}>
            <table
                className={`table align-middle mb-0 ${className}`}
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

function Cell({ className = '', style, ...rest }: TdHTMLAttributes<HTMLTableCellElement>) {
    return <td className={`py-2 ${className}`} style={{ ...style }} {...rest} />;
}

/**
 * Compound table primitive: <Table><Table.Head>...<Table.Row><Table.Cell>.
 * Every list view in the app should use this instead of raw <table> markup.
 */
export const Table = Object.assign(TableRoot, {
    Head,
    HeadCell,
    Row,
    Cell,
});
