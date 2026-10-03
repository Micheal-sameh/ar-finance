import { usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import { useState } from 'react';
import { Offcanvas } from 'react-bootstrap';
import { navConfig } from './navConfig';
import { NavGroup } from './NavGroup';
import { NavItem } from './NavItem';
import type { PlatformShare } from './TenantSwitcher';

const brand = (
    <>
        Avarewase <span style={{ color: 'var(--af-gold)' }}>Finance</span>
    </>
);

export function Sidebar() {
    const [show, setShow] = useState(false);
    const { platform } = usePage<{ platform: PlatformShare | null }>().props;
    // Two entirely separate sidebars, switched by whether a Platform Admin
    // is currently impersonating a tenant: viewing "All tenants" (no active
    // tenant) shows only the Platform section; impersonating a tenant (or
    // being an ordinary tenant user, who never has a `platform` share at
    // all) shows only the ordinary tenant-scoped sections.
    const isPlatformAdminViewingAllTenants = Boolean(platform) && !platform?.isImpersonating;
    const visibleGroups = navConfig.filter((group) => Boolean(group.platformOnly) === isPlatformAdminViewingAllTenants);

    return (
        <>
            <div
                className="d-flex d-lg-none align-items-center justify-content-between"
                style={{
                    backgroundColor: 'var(--af-primary)',
                    padding: '12px 16px',
                    position: 'sticky',
                    top: 0,
                    zIndex: 1020,
                }}
            >
                <div style={{ color: '#fff', fontWeight: 700, fontSize: '16px', letterSpacing: '-0.01em' }}>{brand}</div>
                <button
                    type="button"
                    onClick={() => setShow(true)}
                    aria-label="Open menu"
                    className="d-flex align-items-center justify-content-center"
                    style={{ background: 'transparent', border: 'none', color: '#fff', padding: '4px' }}
                >
                    <Menu size={22} />
                </button>
            </div>

            <Offcanvas
                show={show}
                onHide={() => setShow(false)}
                responsive="lg"
                placement="start"
                className="af-sidebar"
            >
                {/* Bootstrap's responsive offcanvas force-hides .offcanvas-header
                 * at the lg+ breakpoint (it's meant to blend into a navbar),
                 * so the brand can't live there for desktop — it has to be a
                 * non-scrolling child of .offcanvas-body instead, separate
                 * from the scrollable nav wrapper below it. */}
                <Offcanvas.Header closeButton closeVariant="white" className="d-lg-none">
                    <Offcanvas.Title style={{ color: '#fff', fontWeight: 700, fontSize: '16px' }}>{brand}</Offcanvas.Title>
                </Offcanvas.Header>

                <Offcanvas.Body className="d-flex flex-column" style={{ padding: '20px 12px' }} onClick={() => setShow(false)}>
                    <div
                        className="af-sidebar-brand px-2 mb-4 d-none d-lg-flex align-items-center"
                        style={{ color: '#fff', fontWeight: 700, fontSize: '16px', letterSpacing: '-0.01em' }}
                    >
                        <span
                            className="d-flex align-items-center justify-content-center"
                            style={{
                                flexShrink: 0,
                                width: '24px',
                                height: '24px',
                                borderRadius: '6px',
                                backgroundColor: 'var(--af-gold)',
                                color: '#fff',
                                fontSize: '13px',
                            }}
                        >
                            A
                        </span>
                        <span className="af-label">{brand}</span>
                    </div>

                    <div className="af-sidebar-nav">
                        {visibleGroups.map((group) => (
                            <NavGroup key={group.label} label={group.label}>
                                {group.items.map((item) => (
                                    <NavItem
                                        key={item.routeName}
                                        label={item.label}
                                        href={route(item.routeName)}
                                        icon={item.icon}
                                        active={route().current(item.routeName)}
                                    />
                                ))}
                            </NavGroup>
                        ))}
                    </div>
                </Offcanvas.Body>
            </Offcanvas>
        </>
    );
}
