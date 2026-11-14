import { Combobox } from '@headlessui/react';
import { Check, ChevronDown } from 'lucide-react';
import { useMemo, useState } from 'react';
import { InfoTip } from '@/Components/ui/InfoTip';

export interface SearchableOption {
    id: number;
    label: string;
    /** Secondary text shown after the label, e.g. a phone number. Also searched. */
    detail?: string | null;
}

interface SearchableSelectProps {
    label?: string;
    help?: string;
    error?: string;
    options: SearchableOption[];
    value: number | null;
    onChange: (id: number | null) => void;
    /** Label of the "nothing chosen" entry kept at the top of the list, e.g. "Anonymous". */
    emptyLabel: string;
    /** Shown while the box is empty and focused. */
    placeholder?: string;
    noResultsLabel?: string;
}

const EMPTY: SearchableOption = { id: 0, label: '' };

/**
 * A dropdown you can type into to narrow the list — for choices that can
 * grow long, such as donors. Same look as the account picker; the list is
 * rendered next to the field in a portal so a modal never clips it.
 */
export function SearchableSelect({ label, help, error, options, value, onChange, emptyLabel, placeholder, noResultsLabel = 'No matches.' }: SearchableSelectProps) {
    const [query, setQuery] = useState('');
    const empty = useMemo<SearchableOption>(() => ({ ...EMPTY, label: emptyLabel }), [emptyLabel]);
    const selected = options.find((option) => option.id === value) ?? empty;

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (needle === '') return options;

        return options.filter((option) => `${option.label} ${option.detail ?? ''}`.toLowerCase().includes(needle));
    }, [options, query]);

    const showEmpty = query.trim() === '' || emptyLabel.toLowerCase().includes(query.trim().toLowerCase());

    return (
        <div>
            {label && (
                <div className="d-block" style={{ fontSize: '12px', fontWeight: 600, color: '#5e6b80', marginBottom: '6px' }}>
                    {label}
                    {help && (
                        <span className="ms-1">
                            <InfoTip label={`About ${label}`}>{help}</InfoTip>
                        </span>
                    )}
                </div>
            )}

            <Combobox
                immediate
                value={selected}
                onChange={(option: SearchableOption | null) => onChange(option && option.id !== 0 ? option.id : null)}
                onClose={() => setQuery('')}
            >
                <div
                    className="d-flex align-items-center"
                    style={{
                        border: `1px solid ${error ? 'var(--af-danger)' : 'var(--af-border)'}`,
                        borderRadius: 'var(--af-radius-sm)',
                        backgroundColor: 'var(--af-surface)',
                    }}
                >
                    <Combobox.Input
                        className="form-control border-0"
                        style={{ fontSize: '14px', boxShadow: 'none' }}
                        displayValue={(option: SearchableOption | null) => option?.label ?? ''}
                        onChange={(event) => setQuery(event.target.value)}
                        onFocus={(event) => event.target.select()}
                        placeholder={placeholder ?? 'Type to search…'}
                        autoComplete="off"
                    />
                    <Combobox.Button className="btn border-0 px-2" style={{ color: 'var(--af-label)' }} aria-label="Show options">
                        <ChevronDown size={16} />
                    </Combobox.Button>
                </div>

                <Combobox.Options
                    anchor="bottom start"
                    className="py-1"
                    style={{
                        zIndex: 2000,
                        width: 'var(--input-width)',
                        minWidth: '240px',
                        maxHeight: '260px',
                        overflowY: 'auto',
                        backgroundColor: 'var(--af-surface)',
                        border: '1px solid var(--af-border)',
                        borderRadius: 'var(--af-radius-sm)',
                        boxShadow: '0 10px 15px -3px rgb(15 23 42 / 0.1)',
                    }}
                >
                    {[...(showEmpty ? [empty] : []), ...filtered].map((option) => (
                        <Combobox.Option key={option.id} value={option} as="div">
                            {({ focus, selected: isSelected }) => (
                                <div
                                    className="d-flex align-items-center justify-content-between px-3 py-2"
                                    style={{ fontSize: '14px', cursor: 'pointer', backgroundColor: focus ? '#EFF4FE' : 'transparent' }}
                                >
                                    <span style={option.id === 0 ? { color: 'var(--af-label)' } : undefined}>
                                        {option.label}
                                        {option.detail && <span style={{ color: 'var(--af-label)' }}> · {option.detail}</span>}
                                    </span>
                                    {isSelected && <Check size={14} color="var(--af-primary)" />}
                                </div>
                            )}
                        </Combobox.Option>
                    ))}
                    {filtered.length === 0 && !showEmpty && (
                        <div className="px-3 py-2" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                            {noResultsLabel}
                        </div>
                    )}
                    {filtered.length === 0 && showEmpty && query.trim() !== '' && (
                        <div className="px-3 py-2" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                            {noResultsLabel}
                        </div>
                    )}
                </Combobox.Options>
            </Combobox>

            {error && <div style={{ color: 'var(--af-danger)', fontSize: '12px', marginTop: '4px' }}>{error}</div>}
        </div>
    );
}
