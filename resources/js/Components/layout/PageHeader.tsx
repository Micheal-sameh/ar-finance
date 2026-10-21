import type { ReactNode } from 'react';

export interface PageHeaderProps {
    title: string;
    subtitle?: string;
    action?: ReactNode;
}

export function PageHeader({ title, subtitle, action }: PageHeaderProps) {
    return (
        <div className="page-header">
            <div>
                <div className="breadcrumb">
                    Avarewase Finance <span>/</span> {title}
                </div>
                <h1>{title}</h1>
                {subtitle && <p>{subtitle}</p>}
            </div>
            {action}
        </div>
    );
}
