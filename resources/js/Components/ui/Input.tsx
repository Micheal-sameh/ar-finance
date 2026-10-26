import { forwardRef, type InputHTMLAttributes } from 'react';
import { InfoTip } from '@/Components/ui/InfoTip';

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
                    <label htmlFor={inputId} className="d-block" style={{ fontSize: '12px', fontWeight: 600, color: '#5e6b80', marginBottom: '6px' }}>
                        {label}
                        {help && (
                            <span className="ms-1">
                                <InfoTip label={`About ${label}`}>{help}</InfoTip>
                            </span>
                        )}
                    </label>
                )}
                <input
                    ref={ref}
                    id={inputId}
                    className={`form-control fg-control ${className}`}
                    style={{
                        borderColor: error ? 'var(--af-danger)' : 'var(--af-border)',
                        borderRadius: 'var(--af-radius-sm)',
                        fontSize: '13px',
                        ...style,
                    }}
                    {...rest}
                />
                {error ? (
                    <div style={{ color: 'var(--af-danger)', fontSize: '12px', marginTop: '4px' }}>{error}</div>
                ) : help && !label ? (
                    <div style={{ color: 'var(--af-label)', fontSize: '12px', marginTop: '4px' }}>{help}</div>
                ) : null}
            </div>
        );
    },
);

Input.displayName = 'Input';
