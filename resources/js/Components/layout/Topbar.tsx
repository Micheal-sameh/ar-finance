import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, LogOut, User as UserIcon } from 'lucide-react';
import { Dropdown } from 'react-bootstrap';

interface AuthUser {
    name: string;
    email: string;
}

export function Topbar() {
    const { auth } = usePage<{ auth: { user: AuthUser | null } }>().props;
    const user = auth.user;

    if (!user) {
        return null;
    }

    function logout() {
        router.post(route('logout'));
    }

    return (
        <div
            className="d-flex align-items-center justify-content-end"
            style={{
                position: 'sticky',
                top: 0,
                zIndex: 5,
                padding: '10px 0',
                marginBottom: '16px',
                backgroundColor: 'var(--af-bg)',
                borderBottom: '1px solid var(--af-border)',
            }}
        >
            <Dropdown align="end">
                <Dropdown.Toggle
                    as="button"
                    bsPrefix="af-topbar-toggle"
                    className="d-flex align-items-center gap-2 border-0 bg-transparent"
                    style={{ padding: '4px 8px', borderRadius: 'var(--af-radius-sm)' }}
                >
                    <div
                        className="d-flex align-items-center justify-content-center"
                        style={{
                            width: '28px',
                            height: '28px',
                            borderRadius: '50%',
                            backgroundColor: 'var(--af-primary)',
                            color: '#fff',
                            fontSize: '12px',
                            fontWeight: 600,
                        }}
                    >
                        {user.name.charAt(0).toUpperCase()}
                    </div>
                    <span style={{ fontSize: '14px', fontWeight: 500, color: 'var(--af-text)' }}>{user.name}</span>
                    <ChevronDown size={14} style={{ color: 'var(--af-label)' }} />
                </Dropdown.Toggle>

                <Dropdown.Menu style={{ fontSize: '14px', minWidth: '180px' }}>
                    <Dropdown.Item as={Link} href={route('profile.show')} className="d-flex align-items-center gap-2">
                        <UserIcon size={15} />
                        Profile
                    </Dropdown.Item>
                    <Dropdown.Divider />
                    <Dropdown.Item onClick={logout} className="d-flex align-items-center gap-2" style={{ color: 'var(--af-danger)' }}>
                        <LogOut size={15} />
                        Logout
                    </Dropdown.Item>
                </Dropdown.Menu>
            </Dropdown>
        </div>
    );
}
