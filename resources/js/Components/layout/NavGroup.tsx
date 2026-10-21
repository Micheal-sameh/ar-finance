import type { ReactNode } from 'react';

export interface NavGroupProps {
    label: string;
    children: ReactNode;
}

export function NavGroup({ label, children }: NavGroupProps) {
    return (
        <div className="mb-3">
            <div
                className="af-label px-2 mb-1"
                style={{
                    fontSize: '9px',
                    fontWeight: 700,
                    textTransform: 'uppercase',
                    letterSpacing: '0.14em',
                    color: '#71839e',
                    paddingTop: '8px',
                }}
            >
                {label}
            </div>
            <div className="d-flex flex-column gap-1">{children}</div>
        </div>
    );
}
