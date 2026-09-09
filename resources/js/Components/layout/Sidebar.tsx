import { navConfig } from './navConfig';
import { NavGroup } from './NavGroup';
import { NavItem } from './NavItem';

export function Sidebar() {
    return (
        <aside
            style={{
                width: '260px',
                flexShrink: 0,
                minHeight: '100vh',
                backgroundColor: 'var(--af-navy)',
                padding: '20px 12px',
            }}
        >
            <div
                className="px-2 mb-4"
                style={{ color: '#fff', fontWeight: 700, fontSize: '16px', letterSpacing: '-0.01em' }}
            >
                Avarewase <span style={{ color: 'var(--af-gold)' }}>Finance</span>
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
        </aside>
    );
}
