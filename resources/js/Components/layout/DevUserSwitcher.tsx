import { router } from '@inertiajs/react';
import { ChevronDown, Search } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { Dropdown } from 'react-bootstrap';

export interface DevUser {
    id: number;
    name: string;
    email: string;
    code: string | null;
    role: string | null;
}

/**
 * Local-dev-only "log in as" switcher — see HandleInertiaRequests'
 * `devUsers` share (null outside app()->environment('local'), so this
 * never even renders in staging/production) and DevSwitchUserController
 * (the POST target, itself gated the same way).
 */
export function DevUserSwitcher({ users }: { users: DevUser[] }) {
    const [search, setSearch] = useState('');
    const searchRef = useRef<HTMLInputElement>(null);

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();

        if (!term) {
            return users;
        }

        return users.filter(
            (user) =>
                user.name.toLowerCase().includes(term) ||
                user.email.toLowerCase().includes(term) ||
                (user.code?.toLowerCase().includes(term) ?? false),
        );
    }, [search, users]);

    function switchTo(userId: number) {
        router.post(route('dev-switch-user', userId));
    }

    return (
        <Dropdown
            align="end"
            className="me-2"
            onToggle={(isOpen) => {
                if (isOpen) {
                    setSearch('');
                    requestAnimationFrame(() => searchRef.current?.focus());
                }
            }}
        >
            <Dropdown.Toggle
                as="button"
                bsPrefix="af-topbar-toggle"
                className="d-flex align-items-center gap-2"
                style={{
                    padding: '3px 10px',
                    borderRadius: '999px',
                    border: '1px dashed var(--af-warning)',
                    background: 'transparent',
                    color: 'var(--af-warning)',
                    fontSize: '12px',
                    fontWeight: 700,
                    letterSpacing: '0.04em',
                }}
            >
                <span
                    style={{
                        width: '6px',
                        height: '6px',
                        borderRadius: '50%',
                        backgroundColor: 'var(--af-warning)',
                        animation: 'af-dev-switcher-pulse 1.4s ease-in-out infinite',
                    }}
                />
                DEV
                <ChevronDown size={12} />
            </Dropdown.Toggle>

            <Dropdown.Menu style={{ fontSize: '14px', minWidth: '280px', padding: '8px' }}>
                <div className="d-flex align-items-center gap-2 mb-2" style={{ position: 'relative' }}>
                    <Search size={14} style={{ position: 'absolute', left: '8px', color: 'var(--af-label)' }} />
                    <input
                        ref={searchRef}
                        type="text"
                        className="form-control"
                        placeholder="Search users..."
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        onClick={(event) => event.stopPropagation()}
                        style={{ fontSize: '13px', paddingLeft: '28px', borderRadius: 'var(--af-radius-sm)' }}
                    />
                </div>

                <div style={{ maxHeight: '320px', overflowY: 'auto' }}>
                    {filtered.length === 0 ? (
                        <div className="text-center py-3" style={{ color: 'var(--af-label)', fontSize: '13px' }}>
                            No users found
                        </div>
                    ) : (
                        filtered.map((user) => (
                            <Dropdown.Item
                                key={user.id}
                                onClick={() => switchTo(user.id)}
                                className="d-flex align-items-center gap-2"
                                style={{ padding: '6px 8px' }}
                            >
                                <div
                                    className="d-flex align-items-center justify-content-center flex-shrink-0"
                                    style={{
                                        width: '26px',
                                        height: '26px',
                                        borderRadius: '50%',
                                        backgroundColor: 'var(--af-primary)',
                                        color: '#fff',
                                        fontSize: '11px',
                                        fontWeight: 600,
                                    }}
                                >
                                    {user.name.charAt(0).toUpperCase()}
                                </div>
                                <div className="flex-grow-1" style={{ minWidth: 0 }}>
                                    <div className="text-truncate" style={{ fontSize: '13px', fontWeight: 500 }}>
                                        {user.name}
                                    </div>
                                    <div className="text-truncate" style={{ fontSize: '11px', color: 'var(--af-label)' }}>
                                        {[user.role, user.code].filter(Boolean).join(' · ')}
                                    </div>
                                </div>
                            </Dropdown.Item>
                        ))
                    )}
                </div>
            </Dropdown.Menu>
        </Dropdown>
    );
}
