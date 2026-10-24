import { router } from '@inertiajs/react';
import { Building2, ChevronDown } from 'lucide-react';
import { Dropdown } from 'react-bootstrap';

export interface MemberTenants {
    tenants: { id: number; name: string }[];
    activeTenantId: number | null;
}

/**
 * Shown to an ordinary user who belongs to more than one tenant (see
 * HandleInertiaRequests' `memberTenants` share). Platform Admins use
 * TenantSwitcher instead.
 */
export function MemberTenantSwitcher({ memberTenants }: { memberTenants: MemberTenants }) {
    const active = memberTenants.tenants.find((tenant) => tenant.id === memberTenants.activeTenantId);

    return (
        <Dropdown align="end" className="me-2">
            <Dropdown.Toggle
                as="button"
                bsPrefix="af-topbar-toggle"
                className="d-flex align-items-center gap-2 border-0 bg-transparent"
                style={{ padding: '4px 8px', borderRadius: 'var(--af-radius-sm)' }}
            >
                <Building2 size={15} />
                <span style={{ fontSize: '13px', fontWeight: 500, color: 'var(--af-text)' }}>{active?.name ?? 'Select tenant'}</span>
                <ChevronDown size={14} style={{ color: 'var(--af-label)' }} />
            </Dropdown.Toggle>

            <Dropdown.Menu style={{ fontSize: '14px', minWidth: '220px' }}>
                <Dropdown.Header>Switch tenant</Dropdown.Header>
                {memberTenants.tenants.map((tenant) => (
                    <Dropdown.Item
                        key={tenant.id}
                        active={tenant.id === memberTenants.activeTenantId}
                        onClick={() => router.post(route('tenant.switch', tenant.id))}
                    >
                        {tenant.name}
                    </Dropdown.Item>
                ))}
            </Dropdown.Menu>
        </Dropdown>
    );
}
