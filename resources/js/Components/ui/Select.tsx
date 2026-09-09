import { forwardRef, type SelectHTMLAttributes } from 'react';

export interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
    label?: string;
    error?: string;
    help?: string;
}

export const Select = forwardRef<HTMLSelectElement, SelectProps>(
    ({ label, error, help, id, className = '', style, children, ...rest }, ref) => {
        const selectId = id ?? rest.name;

        return (
            <div>
                {label && (
                    <label htmlFor={selectId} className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                        {label}
                    </label>
                )}
                <select
                    ref={ref}
                    id={selectId}
                    className={`form-select ${className}`}
                    style={{
                        borderColor: error ? 'var(--af-danger)' : 'var(--af-border)',
                        borderRadius: 'var(--af-radius-sm)',
                        fontSize: '14px',
                        ...style,
                    }}
                    {...rest}
                >
                    {children}
                </select>
                {error ? (
                    <div style={{ color: 'var(--af-danger)', fontSize: '12px', marginTop: '4px' }}>{error}</div>
                ) : help ? (
                    <div style={{ color: 'var(--af-label)', fontSize: '12px', marginTop: '4px' }}>{help}</div>
                ) : null}
            </div>
        );
    },
);

Select.displayName = 'Select';
