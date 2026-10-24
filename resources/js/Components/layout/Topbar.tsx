import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Menu, User as UserIcon } from 'lucide-react';
import { Dropdown } from 'react-bootstrap';
import { DevUserSwitcher, type DevUser } from './DevUserSwitcher';
import { GlobalSearch } from './GlobalSearch';
import { MemberTenantSwitcher, type MemberTenants } from './MemberTenantSwitcher';

interface AuthUser {
    name: string;
    email: string;
}

export function Topbar({ onOpenSidebar }: { onOpenSidebar: () => void }) {
    const { auth, devUsers, memberTenants } = usePage<{
        auth: { user: AuthUser | null };
        devUsers: DevUser[] | null;
        memberTenants: MemberTenants | null;
    }>().props;
    const user = auth.user;

    if (!user) {
        return null;
    }

    const initials = user.name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');

    return (
        <header className="topbar">
            <div className="d-flex align-items-center gap-2">
                <button type="button" className="icon-btn d-lg-none" aria-label="Open menu" onClick={onOpenSidebar}>
                    <Menu size={20} />
                </button>
                <GlobalSearch />
            </div>
            <div className="top-actions">
                {memberTenants && <MemberTenantSwitcher memberTenants={memberTenants} />}
                {devUsers && <DevUserSwitcher users={devUsers} />}
                <Dropdown align="end">
                    <Dropdown.Toggle as="button" bsPrefix="af-topbar-toggle" className="top-avatar border-0" aria-label="Account menu">
                        {initials}
                    </Dropdown.Toggle>
                    <Dropdown.Menu style={{ fontSize: '13.5px', minWidth: '180px' }}>
                        <Dropdown.Header>{user.name}</Dropdown.Header>
                        <Dropdown.Item as={Link} href={route('profile.show')} className="d-flex align-items-center gap-2">
                            <UserIcon size={15} />
                            Profile
                        </Dropdown.Item>
                        <Dropdown.Divider />
                        <Dropdown.Item
                            onClick={() => router.post(route('logout'))}
                            className="d-flex align-items-center gap-2"
                            style={{ color: 'var(--af-danger)' }}
                        >
                            <LogOut size={15} />
                            Logout
                        </Dropdown.Item>
                    </Dropdown.Menu>
                </Dropdown>
            </div>
        </header>
    );
}
