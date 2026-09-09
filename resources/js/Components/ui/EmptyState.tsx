import type { ReactNode } from 'react';

export interface EmptyStateProps {
    icon?: ReactNode;
    title: string;
    description?: string;
    action?: ReactNode;
}

export function EmptyState({ icon, title, description, action }: EmptyStateProps) {
    return (
        <div className="text-center py-5">
            {icon && (
                <div
                    className="d-inline-flex align-items-center justify-content-center mb-3"
                    style={{
                        width: '48px',
                        height: '48px',
                        borderRadius: '999px',
                        backgroundColor: '#EEF1F6',
                        color: 'var(--af-label)',
                    }}
                >
                    {icon}
                </div>
            )}
            <div style={{ color: 'var(--af-text)', fontWeight: 600, fontSize: '15px' }}>{title}</div>
            {description && (
                <div style={{ color: 'var(--af-label)', fontSize: '13px', marginTop: '4px' }}>{description}</div>
            )}
            {action && <div className="mt-3">{action}</div>}
        </div>
    );
}
