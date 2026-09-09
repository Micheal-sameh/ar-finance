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
            className="d-flex align-items-center gap-2 text-decoration-none"
            style={{
                padding: '8px 12px',
                borderRadius: 'var(--af-radius-sm)',
                fontSize: '14px',
                color: active ? '#fff' : 'rgba(255,255,255,0.75)',
                backgroundColor: active ? 'rgba(255,255,255,0.18)' : 'transparent',
            }}
        >
            <Icon size={16} />
            <span>{label}</span>
        </Link>
    );
}
