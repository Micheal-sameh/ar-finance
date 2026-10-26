import { Info } from 'lucide-react';
import { useState } from 'react';

export interface InfoTipProps {
    /** Plain-language explanation of the field, written for someone using it for the first time. */
    children: string;
    /** Accessible name of the trigger button. */
    label?: string;
}

/**
 * Small ⓘ button that opens a click-to-toggle explanation. Used beside form
 * labels (see Input/Select's `help` prop) so new users can learn what a
 * field means without leaving the form. A native `title` tooltip is
 * deliberately avoided: it only shows on hover, never on tap or click.
 */
export function InfoTip({ children, label = 'More information' }: InfoTipProps) {
    const [open, setOpen] = useState(false);

    return (
        <span style={{ position: 'relative', display: 'inline-flex', lineHeight: 0, verticalAlign: 'middle' }}>
            <button
                type="button"
                className="btn p-0 border-0"
                style={{ lineHeight: 0, color: 'var(--af-label)' }}
                onClick={() => setOpen((current) => !current)}
                onBlur={() => setOpen(false)}
                aria-label={label}
                aria-expanded={open}
            >
                <Info size={13} />
            </button>
            {open && (
                <span
                    role="tooltip"
                    style={{
                        position: 'absolute',
                        top: '22px',
                        left: '-8px',
                        zIndex: 20,
                        width: '250px',
                        padding: '8px 10px',
                        background: 'var(--af-surface)',
                        border: '1px solid var(--af-border)',
                        borderRadius: 'var(--af-radius-sm)',
                        boxShadow: '0 4px 14px rgba(0,0,0,0.12)',
                        fontSize: '12px',
                        fontWeight: 400,
                        lineHeight: 1.4,
                        color: 'var(--af-label)',
                        textAlign: 'start',
                        whiteSpace: 'normal',
                    }}
                >
                    {children}
                </span>
            )}
        </span>
    );
}
