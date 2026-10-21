import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Icon } from './Icon';
import { visibleNavGroups } from './navConfig';
import type { PlatformShare } from './TenantSwitcher';

interface SearchEntry {
    label: string;
    group: string;
    routeName: string;
}

/**
 * Jump-to-page search: matches the pages the user may open (the same
 * permission-filtered set the sidebar shows). Opens from the topbar box or
 * with Ctrl/⌘ + K.
 */
export function GlobalSearch() {
    const { platform, auth } = usePage<{ platform: PlatformShare | null; auth: { permissions: string[] } }>().props;
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const inputRef = useRef<HTMLInputElement>(null);

    const entries = useMemo<SearchEntry[]>(() => {
        const viewingAllTenants = Boolean(platform) && !platform?.isImpersonating;

        return visibleNavGroups(auth.permissions, viewingAllTenants).flatMap((group) =>
            group.items.map((item) => ({ label: item.label, group: group.label, routeName: item.routeName })),
        );
    }, [auth.permissions, platform]);

    const results = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return needle === ''
            ? entries
            : entries.filter((entry) => `${entry.label} ${entry.group}`.toLowerCase().includes(needle));
    }, [entries, query]);

    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setOpen(true);
            }
        }

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    useEffect(() => {
        if (open) {
            setQuery('');
            setActive(0);
            inputRef.current?.focus();
        }
    }, [open]);

    function go(entry: SearchEntry | undefined) {
        if (!entry) {
            return;
        }
        setOpen(false);
        router.get(route(entry.routeName));
    }

    function onInputKeyDown(event: React.KeyboardEvent) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((index) => Math.min(index + 1, results.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((index) => Math.max(index - 1, 0));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            go(results[active]);
        } else if (event.key === 'Escape') {
            setOpen(false);
        }
    }

    return (
        <>
            <button type="button" className="global-search" onClick={() => setOpen(true)}>
                <Icon name="search" />
                <span>Search pages, reports, accounts...</span>
                <kbd>Ctrl K</kbd>
            </button>

            {open && (
                <div className="drawer-backdrop search-backdrop" onClick={() => setOpen(false)}>
                    <div className="search-palette" role="dialog" aria-label="Search" onClick={(event) => event.stopPropagation()}>
                        <div className="search-input">
                            <Icon name="search" />
                            <input
                                ref={inputRef}
                                value={query}
                                onChange={(event) => {
                                    setQuery(event.target.value);
                                    setActive(0);
                                }}
                                onKeyDown={onInputKeyDown}
                                placeholder="Search pages..."
                            />
                            <kbd>Esc</kbd>
                        </div>
                        <div className="search-results">
                            {results.length === 0 && <div className="search-empty">No pages match “{query}”.</div>}
                            {results.map((entry, index) => (
                                <button
                                    type="button"
                                    key={`${entry.group}-${entry.routeName}-${entry.label}`}
                                    className={index === active ? 'active' : ''}
                                    onMouseEnter={() => setActive(index)}
                                    onClick={() => go(entry)}
                                >
                                    <span>{entry.label}</span>
                                    <small>{entry.group}</small>
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
