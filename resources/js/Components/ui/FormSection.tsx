import type { ReactNode } from 'react';

export interface FormSectionProps {
    title: string;
    description?: string;
    /** Optional control shown on the right of the heading, e.g. an "Add" button. */
    action?: ReactNode;
    /** Subtle background, used to set apart a section only some roles see. */
    tinted?: boolean;
    children: ReactNode;
}

/** Titled group of related fields inside a long modal form. */
export function FormSection({ title, description, action, tinted = false, children }: FormSectionProps) {
    return (
        <section
            className="d-flex flex-column gap-3"
            style={
                tinted
                    ? { padding: '16px', borderRadius: 'var(--af-radius)', border: '1px solid var(--af-border)', backgroundColor: 'var(--af-bg, rgba(15, 23, 42, 0.03))' }
                    : undefined
            }
        >
            <div className="d-flex align-items-start justify-content-between gap-2" style={tinted ? undefined : { borderBottom: '1px solid var(--af-border)', paddingBottom: '8px' }}>
                <div>
                    <h3 style={{ fontSize: '14px', fontWeight: 600, color: 'var(--af-navy)', margin: 0 }}>{title}</h3>
                    {description && <p style={{ fontSize: '12.5px', color: 'var(--af-label)', margin: '2px 0 0' }}>{description}</p>}
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}
