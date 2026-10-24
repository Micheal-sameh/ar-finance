import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { Icon } from './Icon';
import { visibleNavGroups } from './navConfig';
import type { PlatformShare } from './TenantSwitcher';

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

export function Sidebar({
    open,
    onClose,
    collapsed,
    onToggleCollapsed,
}: {
    open: boolean;
    onClose: () => void;
    collapsed: boolean;
    onToggleCollapsed: () => void;
}) {
    const [branchOpen, setBranchOpen] = useState(false);
    const navRef = useRef<HTMLElement>(null);
    const { platform, auth } = usePage<{
        platform: PlatformShare | null;
        auth: { permissions: string[]; user: { name: string; email: string } | null };
    }>().props;
    // Two entirely separate sidebars, switched by whether a Platform Admin
    // is currently impersonating a tenant: viewing "All tenants" (no active
    // tenant) shows only the Platform section; impersonating a tenant (or
    // being an ordinary tenant user, who never has a `platform` share at
    // all) shows only the ordinary tenant-scoped sections.
    const isPlatformAdminViewingAllTenants = Boolean(platform) && !platform?.isImpersonating;
    const visibleGroups = visibleNavGroups(auth.permissions, isPlatformAdminViewingAllTenants);

    // Keep the nav where the user left it across full page loads, then make
    // sure the current page's link is on screen after every navigation.
    useLayoutEffect(() => {
        try {
            const saved = Number(sessionStorage.getItem('af.sidebarScroll'));

            if (navRef.current && saved > 0) {
                navRef.current.scrollTop = saved;
            }
        } catch {
            // storage unavailable: start at the top
        }
    }, []);

    const currentUrl = usePage().url;

    useEffect(() => {
        navRef.current?.querySelector('.nav-item.active')?.scrollIntoView({ block: 'nearest' });
    }, [currentUrl]);

    const activeTenant = platform?.tenants.find((tenant) => tenant.id === platform.activeTenantId);
    const user = auth.user;

    return (
        <aside className={`sidebar ${open ? 'open' : ''}`} onClick={onClose}>
            <button
                type="button"
                className="sidebar-toggle"
                aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                onClick={(event) => {
                    event.stopPropagation();
                    onToggleCollapsed();
                }}
            >
                <Icon name="chevron" size={14} />
            </button>

            <div className="brand">
                <div>
                    <b>Avarewase</b>
                    <small>FINANCE</small>
                </div>
            </div>

            {platform && (
                <div className="branch-wrap" onClick={(event) => event.stopPropagation()}>
                    <button type="button" className="branch-switcher" onClick={() => setBranchOpen(!branchOpen)}>
                        <span className="branch-icon">
                            <Icon name="bank" size={17} />
                        </span>
                        <span>
                            <small>ORGANIZATION</small>
                            <b>{activeTenant ? activeTenant.name : 'All services'}</b>
                        </span>
                        <Icon name="chevron" size={15} />
                    </button>
                    {branchOpen && (
                        <div className="branch-menu">
                            <button
                                type="button"
                                className={!activeTenant ? 'selected' : ''}
                                onClick={() => {
                                    setBranchOpen(false);
                                    router.post(route('platform.stop-impersonating'));
                                }}
                            >
                                All services
                                <span>{!activeTenant && <Icon name="check" size={14} />}</span>
                            </button>
                            {platform.tenants.map((tenant) => (
                                <button
                                    type="button"
                                    key={tenant.id}
                                    className={activeTenant?.id === tenant.id ? 'selected' : ''}
                                    onClick={() => {
                                        setBranchOpen(false);
                                        router.post(route('platform.switch-tenant', tenant.id));
                                    }}
                                >
                                    {tenant.name}
                                    <span>{activeTenant?.id === tenant.id && <Icon name="check" size={14} />}</span>
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            )}

            <nav
                ref={navRef}
                onScroll={(event) => {
                    try {
                        sessionStorage.setItem('af.sidebarScroll', String(event.currentTarget.scrollTop));
                    } catch {
                        // storage unavailable: scroll position just isn't remembered
                    }
                }}
            >
                {visibleGroups.map((group) => (
                    <div key={group.label}>
                        <div className="nav-group">{group.label.toUpperCase()}</div>
                        {group.items.map((item) => (
                            <Link
                                key={item.routeName}
                                href={route(item.routeName)}
                                title={item.label}
                                className={`nav-item ${route().current(item.routeName) ? 'active' : ''}`}
                            >
                                <item.icon size={18} strokeWidth={1.8} />
                                <span>{item.label}</span>
                            </Link>
                        ))}
                    </div>
                ))}
            </nav>

            {user && (
                <div className="sidebar-user">
                    <div className="avatar">{initials(user.name)}</div>
                    <div>
                        <b>{user.name}</b>
                        <small>{user.email}</small>
                    </div>
                    <button type="button" aria-label="Log out" onClick={() => router.post(route('logout'))}>
                        <Icon name="logout" size={17} />
                    </button>
                </div>
            )}
        </aside>
    );
}
