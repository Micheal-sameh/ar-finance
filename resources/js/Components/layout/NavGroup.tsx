import type { ReactNode } from 'react';

export interface NavGroupProps {
    label: string;
    children: ReactNode;
}

export function NavGroup({ label, children }: NavGroupProps) {
    return (
        <div className="mb-3">
            <div
                className="px-2 mb-1"
                style={{
                    fontSize: '11px',
                    textTransform: 'uppercase',
                    letterSpacing: '0.05em',
                    color: 'rgba(255,255,255,0.45)',
                }}
            >
                {label}
            </div>
            <div className="d-flex flex-column gap-1">{children}</div>
        </div>
    );
}
