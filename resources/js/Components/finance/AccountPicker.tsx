import { Combobox } from '@headlessui/react';
import { Check, ChevronDown } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useAccounts } from '@/hooks/useAccounts';
import type { AccountOption, AccountType } from '@/types/finance';

export interface AccountPickerProps {
    value: number | null;
    onChange: (accountId: number | null) => void;
    error?: string;
    placeholder?: string;
    filterType?: AccountType;
    dropUp?: boolean;
}

/**
 * Chart-of-accounts combobox, fed by the shared useAccounts() hook — the
 * one component reused everywhere an account is chosen (journal lines,
 * report filters, expense categorization, ...).
 */
export function AccountPicker({ value, onChange, error, placeholder = 'Select account…', filterType, dropUp = false }: AccountPickerProps) {
    const { accounts, loading } = useAccounts();
    const [query, setQuery] = useState('');

    const selected = accounts.find((a) => a.id === value) ?? null;

    const filtered = useMemo(() => {
        const byType = filterType ? accounts.filter((a) => a.type === filterType) : accounts;

        if (query === '') return byType;
        const q = query.toLowerCase();

        return byType.filter(
            (a) => a.code.toLowerCase().includes(q) || a.name.toLowerCase().includes(q),
        );
    }, [accounts, filterType, query]);

    return (
        <Combobox value={selected} onChange={(account: AccountOption | null) => onChange(account?.id ?? null)}>
            <div className="position-relative">
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
                        displayValue={(account: AccountOption | null) => (account ? `${account.code} · ${account.name}` : '')}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder={loading ? 'Loading accounts…' : placeholder}
                    />
                    <Combobox.Button className="btn border-0 px-2" style={{ color: 'var(--af-label)' }}>
                        <ChevronDown size={16} />
                    </Combobox.Button>
                </div>

                <Combobox.Options
                    className={`position-absolute w-100 py-1 ${dropUp ? 'mb-1' : 'mt-1'}`}
                    style={{
                        zIndex: 20,
                        maxHeight: '260px',
                        overflowY: 'auto',
                        backgroundColor: 'var(--af-surface)',
                        border: '1px solid var(--af-border)',
                        borderRadius: 'var(--af-radius-sm)',
                        boxShadow: '0 10px 15px -3px rgb(15 23 42 / 0.1)',
                        ...(dropUp ? { bottom: '100%' } : { top: '100%' }),
                    }}
                >
                    {filtered.length === 0 && (
                        <div className="px-3 py-2" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                            No matching accounts.
                        </div>
                    )}
                    {filtered.map((account) => (
                        <Combobox.Option key={account.id} value={account} as="div">
                            {({ active, selected: isSelected }) => (
                                <div
                                    className="d-flex align-items-center justify-content-between px-3 py-2"
                                    style={{
                                        fontSize: '14px',
                                        cursor: 'pointer',
                                        backgroundColor: active ? '#EFF4FE' : 'transparent',
                                    }}
                                >
                                    <span>
                                        <span style={{ color: 'var(--af-label)' }}>{account.code}</span>{' '}
                                        {account.name}
                                    </span>
                                    {isSelected && <Check size={14} color="var(--af-primary)" />}
                                </div>
                            )}
                        </Combobox.Option>
                    ))}
                </Combobox.Options>
            </div>
            {error && <div style={{ color: 'var(--af-danger)', fontSize: '12px', marginTop: '4px' }}>{error}</div>}
        </Combobox>
    );
}
