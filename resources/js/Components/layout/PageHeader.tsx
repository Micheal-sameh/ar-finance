import type { ReactNode } from 'react';

export interface PageHeaderProps {
    title: string;
    subtitle?: string;
    action?: ReactNode;
}

export function PageHeader({ title, subtitle, action }: PageHeaderProps) {
    return (
        <div className="d-flex align-items-start justify-content-between mb-4">
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
