import { forwardRef, type SelectHTMLAttributes } from 'react';
import { InfoTip } from '@/Components/ui/InfoTip';

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
                    <label htmlFor={selectId} className="d-block" style={{ fontSize: '12px', fontWeight: 600, color: '#5e6b80', marginBottom: '6px' }}>
                        {label}
                        {help && (
                            <span className="ms-1">
                                <InfoTip label={`About ${label}`}>{help}</InfoTip>
                            </span>
                        )}
                    </label>
                )}
                <select
                    ref={ref}
                    id={selectId}
                    className={`form-select fg-control ${className}`}
                    style={{
                        borderColor: error ? 'var(--af-danger)' : 'var(--af-border)',
                        borderRadius: 'var(--af-radius-sm)',
                        fontSize: '13px',
                        ...style,
                    }}
                    {...rest}
                >
                    {children}
                </select>
                {error ? (
                    <div style={{ color: 'var(--af-danger)', fontSize: '12px', marginTop: '4px' }}>{error}</div>
                ) : help && !label ? (
                    <div style={{ color: 'var(--af-label)', fontSize: '12px', marginTop: '4px' }}>{help}</div>
                ) : null}
            </div>
        );
    },
);

Select.displayName = 'Select';
