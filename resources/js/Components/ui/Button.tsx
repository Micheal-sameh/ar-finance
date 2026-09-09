import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from 'react';

export type ButtonVariant = 'primary' | 'outline' | 'danger' | 'ghost';
export type ButtonSize = 'sm' | 'md';

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: ButtonVariant;
    size?: ButtonSize;
    loading?: boolean;
    leadingIcon?: ReactNode;
}

const variantStyles: Record<ButtonVariant, React.CSSProperties> = {
    primary: {
        backgroundColor: 'var(--af-primary)',
        borderColor: 'var(--af-primary)',
        color: '#fff',
    },
    outline: {
        backgroundColor: 'transparent',
        borderColor: 'var(--af-border)',
        color: 'var(--af-text)',
    },
    danger: {
        backgroundColor: 'var(--af-danger)',
        borderColor: 'var(--af-danger)',
        color: '#fff',
    },
    ghost: {
        backgroundColor: 'transparent',
        borderColor: 'transparent',
        color: 'var(--af-label)',
    },
};

const sizeStyles: Record<ButtonSize, React.CSSProperties> = {
    sm: { padding: '5px 12px', fontSize: '13px' },
    md: { padding: '8px 16px', fontSize: '14px' },
};

/**
 * Domain-agnostic button. Colors resolve from theme.ts / CSS variables via
 * `variant`, never hardcoded per call site.
 */
export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
    ({ variant = 'primary', size = 'md', loading = false, leadingIcon, disabled, className = '', style, children, ...rest }, ref) => {
        return (
            <button
                ref={ref}
                disabled={disabled || loading}
                className={`btn d-inline-flex align-items-center gap-2 fw-medium ${className}`}
                style={{
                    borderRadius: 'var(--af-radius-sm)',
                    border: '1px solid',
                    lineHeight: 1.2,
                    opacity: disabled || loading ? 0.6 : 1,
                    ...variantStyles[variant],
                    ...sizeStyles[size],
                    ...style,
                }}
                {...rest}
            >
                {loading && (
                    <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true" />
                )}
                {!loading && leadingIcon}
                {children}
            </button>
        );
    },
);

Button.displayName = 'Button';
