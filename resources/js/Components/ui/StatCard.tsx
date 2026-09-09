import type { ReactNode } from 'react';
import { type Variant, variantColors } from '@/theme';
import { Card } from './Card';

export interface StatCardProps {
    label: string;
    value: string;
    sub?: string;
    icon?: ReactNode;
    color?: Variant;
}

/**
 * KPI card: label/value/sub with a colored icon box. Reused across the
 * Dashboard, Cost Centers, and any report summary row.
 */
export function StatCard({ label, value, sub, icon, color = 'primary' }: StatCardProps) {
    const tone = variantColors[color];

    return (
        <Card>
            <div className="d-flex align-items-start justify-content-between">
                <div>
                    <div style={{ color: 'var(--af-label)', fontSize: '13px', marginBottom: '6px' }}>{label}</div>
                    <div style={{ color: 'var(--af-text)', fontSize: '24px', fontWeight: 600, lineHeight: 1.2 }}>
                        {value}
                    </div>
                    {sub && (
                        <div style={{ color: 'var(--af-label)', fontSize: '12px', marginTop: '4px' }}>{sub}</div>
                    )}
                </div>
                {icon && (
                    <div
                        style={{
                            width: '48px',
                            height: '48px',
                            borderRadius: 'var(--af-radius-sm)',
                            backgroundColor: tone.bg,
                            color: tone.fg,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            flexShrink: 0,
                        }}
                    >
                        {icon}
                    </div>
                )}
            </div>
        </Card>
    );
}
