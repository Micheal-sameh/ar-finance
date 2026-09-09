import type { HTMLAttributes } from 'react';
import { type Variant, variantColors } from '@/theme';

export interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
    variant?: Variant;
}

/**
 * Status pill. Takes a semantic `variant`, not a hardcoded color per
 * feature — resolves through theme.ts so every badge in the app stays
 * visually consistent.
 */
export function Badge({ variant = 'neutral', className = '', style, children, ...rest }: BadgeProps) {
    const tone = variantColors[variant];

    return (
        <span
            className={`d-inline-flex align-items-center fw-medium ${className}`}
            style={{
                color: tone.fg,
                backgroundColor: tone.bg,
                border: `1px solid ${tone.border}`,
                borderRadius: '999px',
                padding: '2px 10px',
                fontSize: '12px',
                lineHeight: 1.6,
                ...style,
            }}
            {...rest}
        >
            {children}
        </span>
    );
}
