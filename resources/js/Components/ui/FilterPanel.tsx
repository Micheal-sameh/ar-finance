import { ChevronDown, ChevronUp, SlidersHorizontal } from 'lucide-react';
import { type ReactNode, useState } from 'react';

export interface FilterPanelProps {
    /** Whether any filter currently has a value — panel starts expanded when true. */
    active?: boolean;
    children: ReactNode;
}

/**
 * Collapses a multi-field filter bar behind a toggle so it doesn't push the
 * table below the fold on mobile, where each field stacks full-width. Only
 * mobile collapses — the toggle is hidden and the content forced open at
 * the md breakpoint and up, where the row-based layout doesn't need it.
 * Starts expanded when a filter is already applied, so an active filter is
 * never hidden from view on load.
 */
export function FilterPanel({ active = false, children }: FilterPanelProps) {
    const [open, setOpen] = useState(active);

    return (
        <div>
            <button
                type="button"
                className="d-flex d-md-none align-items-center gap-1 mb-2"
                onClick={() => setOpen((prev) => !prev)}
                style={{
                    background: 'none',
                    border: 'none',
                    padding: 0,
                    fontSize: '13px',
                    fontWeight: 500,
                    color: 'var(--af-label)',
                }}
            >
                <SlidersHorizontal size={14} />
                Filters
                {active && (
                    <span
                        style={{
                            width: '6px',
                            height: '6px',
                            borderRadius: '999px',
                            backgroundColor: 'var(--af-primary)',
                            display: 'inline-block',
                        }}
                    />
                )}
                {open ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
            </button>
            <div className={open ? undefined : 'af-filter-panel-collapsed'}>{children}</div>
        </div>
    );
}
