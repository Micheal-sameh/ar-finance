import type { HTMLAttributes, ReactNode } from 'react';

export interface CardProps extends HTMLAttributes<HTMLDivElement> {
    children: ReactNode;
    padded?: boolean;
}

/**
 * The bordered white container used everywhere. Never re-declare this
 * border/radius/shadow combination inline in a page.
 */
export function Card({ children, padded = true, className = '', style, ...rest }: CardProps) {
    return (
        <div
            className={className}
            style={{
                backgroundColor: 'var(--af-surface)',
                border: '1px solid var(--af-border)',
                borderRadius: 'var(--af-radius)',
                boxShadow: 'var(--af-shadow)',
                padding: padded ? '20px' : 0,
                ...style,
            }}
            {...rest}
        >
            {children}
        </div>
    );
}
