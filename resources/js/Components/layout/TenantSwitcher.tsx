import { router } from '@inertiajs/react';
import { Building2, ChevronDown, Globe } from 'lucide-react';
import { Dropdown } from 'react-bootstrap';
import type { Tenant } from '@/types/finance';

export interface PlatformShare {
    tenants: Tenant[];
    activeTenantId: number | null;
    isImpersonating: boolean;
}

/**
 * Only rendered for a Platform Admin (see HandleInertiaRequests' `platform`
 * share) — lets them switch into any tenant's data, or back out to the
 * cross-tenant Platform view. Switching posts to the session-based
 * impersonation routes in routes/web.php (`platform.switch-tenant` /
 * `platform.stop-impersonating`).
 */
export function TenantSwitcher({ platform }: { platform: PlatformShare }) {
    const activeTenant = platform.tenants.find((tenant) => tenant.id === platform.activeTenantId);

    function switchInto(tenantId: number) {
        router.post(route('platform.switch-tenant', tenantId));
    }

    function stopImpersonating() {
        router.post(route('platform.stop-impersonating'));
    }

    return (
        <Dropdown align="end" className="me-2">
            <Dropdown.Toggle
                as="button"
                bsPrefix="af-topbar-toggle"
                className="d-flex align-items-center gap-2 border-0 bg-transparent"
                style={{ padding: '4px 8px', borderRadius: 'var(--af-radius-sm)' }}
            >
                {activeTenant ? <Building2 size={15} /> : <Globe size={15} />}
                <span style={{ fontSize: '13px', fontWeight: 500, color: 'var(--af-text)' }}>
                    {activeTenant ? activeTenant.name : 'All tenants'}
                </span>
                <ChevronDown size={14} style={{ color: 'var(--af-label)' }} />
            </Dropdown.Toggle>

            <Dropdown.Menu style={{ fontSize: '14px', minWidth: '220px' }}>
                <Dropdown.Header>Switch tenant</Dropdown.Header>
                {platform.tenants.map((tenant) => (
                    <Dropdown.Item
                        key={tenant.id}
                        active={tenant.id === platform.activeTenantId}
                        disabled={!tenant.is_active}
                        onClick={() => switchInto(tenant.id)}
                    >
                        {tenant.name}
                        {!tenant.is_active && ' (inactive)'}
                    </Dropdown.Item>
                ))}
                <Dropdown.Divider />
                <Dropdown.Item active={!platform.isImpersonating} onClick={stopImpersonating}>
                    All tenants (Platform view)
                </Dropdown.Item>
            </Dropdown.Menu>
        </Dropdown>
    );
}
