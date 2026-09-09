import { Menu } from 'lucide-react';
import { useState } from 'react';
import { Offcanvas } from 'react-bootstrap';
import { navConfig } from './navConfig';
import { NavGroup } from './NavGroup';
import { NavItem } from './NavItem';

const brand = (
    <>
        Avarewase <span style={{ color: 'var(--af-gold)' }}>Finance</span>
    </>
);

export function Sidebar() {
    const [show, setShow] = useState(false);

    return (
        <>
            <div
                className="d-flex d-lg-none align-items-center justify-content-between"
                style={{ backgroundColor: 'var(--af-primary)', padding: '12px 16px' }}
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
                style={{ width: '260px', flexShrink: 0 }}
            >
                <Offcanvas.Header closeButton closeVariant="white" className="d-lg-none">
                    <Offcanvas.Title style={{ color: '#fff', fontWeight: 700, fontSize: '16px' }}>{brand}</Offcanvas.Title>
                </Offcanvas.Header>

                <Offcanvas.Body className="d-flex flex-column" style={{ padding: '20px 12px' }} onClick={() => setShow(false)}>
                    <div className="px-2 mb-4 d-none d-lg-block" style={{ color: '#fff', fontWeight: 700, fontSize: '16px', letterSpacing: '-0.01em' }}>
                        {brand}
                    </div>

                    {navConfig.map((group) => (
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
                </Offcanvas.Body>
            </Offcanvas>
        </>
    );
}
