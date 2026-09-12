import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export interface NavItemProps {
    label: string;
    href: string;
    icon: LucideIcon;
    active?: boolean;
}

export function NavItem({ label, href, icon: Icon, active = false }: NavItemProps) {
    return (
        <Link
            href={href}
            title={label}
            className="d-flex align-items-center text-decoration-none"
            style={{
                padding: '8px 12px',
                borderRadius: 'var(--af-radius-sm)',
                fontSize: '14px',
                color: active ? '#fff' : 'rgba(255,255,255,0.75)',
                backgroundColor: active ? 'rgba(255,255,255,0.18)' : 'transparent',
            }}
        >
            <Icon size={16} className="af-nav-icon" />
            <span className="af-label" style={{ marginLeft: '8px' }}>
                {label}
            </span>
        </Link>
    );
}
