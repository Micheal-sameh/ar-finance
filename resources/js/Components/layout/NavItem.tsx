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
                height: '36px',
                padding: '0 10px',
                borderRadius: 'var(--af-radius-sm)',
                fontSize: '12.5px',
                color: active ? '#fff' : '#bec8d7',
                backgroundColor: active ? 'var(--af-primary)' : 'transparent',
                boxShadow: active ? '0 4px 12px rgba(0,0,0,0.16)' : 'none',
            }}
        >
            <Icon size={16} className="af-nav-icon" />
            <span className="af-label" style={{ marginLeft: '8px' }}>
                {label}
            </span>
        </Link>
    );
}
