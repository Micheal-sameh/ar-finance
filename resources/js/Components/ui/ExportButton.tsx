import { Download } from 'lucide-react';
import type { AnchorHTMLAttributes } from 'react';

interface ExportButtonProps extends AnchorHTMLAttributes<HTMLAnchorElement> {
    label?: string;
}

/**
 * Styled like Button's outline variant but rendered as a plain <a> — an
 * Excel export is a GET download (like the accounts import template),
 * not a POST action, so it can't go through <Button>'s onClick handler.
 */
export function ExportButton({ label = 'Export', className = '', style, ...rest }: ExportButtonProps) {
    return (
        <a
            className={`btn d-inline-flex align-items-center gap-2 fw-semibold text-decoration-none ${className}`}
            style={{
                borderRadius: 'var(--af-radius-sm)',
                border: '1px solid',
                lineHeight: 1.2,
                backgroundColor: 'var(--af-surface)',
                borderColor: 'var(--af-border)',
                color: '#44526a',
                padding: '0 13px',
                height: '36px',
                fontSize: '12.5px',
                ...style,
            }}
            {...rest}
        >
            <Download size={16} />
            {label}
        </a>
    );
}
