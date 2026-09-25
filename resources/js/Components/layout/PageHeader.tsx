import type { ReactNode } from 'react';

export interface PageHeaderProps {
    title: string;
    subtitle?: string;
    action?: ReactNode;
}

export function PageHeader({ title, subtitle, action }: PageHeaderProps) {
    return (
        <div className="d-flex flex-column flex-md-row align-items-md-start justify-content-md-between gap-2 mb-4">
            <div>
                <h1 style={{ fontSize: '20px', fontWeight: 600, color: 'var(--af-navy)', marginBottom: subtitle ? '4px' : 0 }}>
                    {title}
                </h1>
                {subtitle && <div style={{ fontSize: '13px', color: 'var(--af-label)' }}>{subtitle}</div>}
            </div>
            {action}
        </div>
    );
}
