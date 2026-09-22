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
export function ExportButton({ label = 'Export to Excel', className = '', style, ...rest }: ExportButtonProps) {
    return (
        <a
            className={`btn d-inline-flex align-items-center gap-2 fw-medium text-decoration-none ${className}`}
            style={{
                borderRadius: 'var(--af-radius-sm)',
                border: '1px solid',
                lineHeight: 1.2,
                backgroundColor: 'transparent',
                borderColor: 'var(--af-border)',
                color: 'var(--af-text)',
                padding: '8px 16px',
                fontSize: '14px',
                ...style,
            }}
            {...rest}
        >
            <Download size={16} />
            {label}
        </a>
    );
}
