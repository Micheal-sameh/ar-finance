import { forwardRef, type InputHTMLAttributes } from 'react';

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
    label?: string;
    error?: string;
    help?: string;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
    ({ label, error, help, id, className = '', style, ...rest }, ref) => {
        const inputId = id ?? rest.name;

        return (
            <div>
                {label && (
                    <label htmlFor={inputId} className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                        {label}
                    </label>
                )}
                <input
                    ref={ref}
                    id={inputId}
                    className={`form-control ${className}`}
                    style={{
                        borderColor: error ? 'var(--af-danger)' : 'var(--af-border)',
                        borderRadius: 'var(--af-radius-sm)',
                        fontSize: '14px',
                        ...style,
                    }}
                    {...rest}
                />
                {error ? (
                    <div style={{ color: 'var(--af-danger)', fontSize: '12px', marginTop: '4px' }}>{error}</div>
                ) : help ? (
                    <div style={{ color: 'var(--af-label)', fontSize: '12px', marginTop: '4px' }}>{help}</div>
                ) : null}
            </div>
        );
    },
);

Input.displayName = 'Input';
