import { useEffect, useState } from 'react';
import api from '@/lib/api';
import type { AccountOption } from '@/types/finance';

interface UseAccountsResult {
    accounts: AccountOption[];
    loading: boolean;
    error: string | null;
}

const pending = new Map<number | null, Promise<AccountOption[]>>();

/**
 * Pickers that mount together (e.g. the GL mapping page) share one request;
 * the entry is dropped once it settles so a later visit sees fresh data.
 */
function fetchAccounts(tenantId: number | null): Promise<AccountOption[]> {
    const existing = pending.get(tenantId);
    if (existing) return existing;

    const request = api
        .get<AccountOption[]>(route('accounts.options'), { params: tenantId !== null ? { for_tenant: tenantId } : undefined })
        .then((response) => response.data)
        .finally(() => pending.delete(tenantId));

    pending.set(tenantId, request);

    return request;
}

/**
 * Single shared source of the active chart of accounts for any picker in
 * the app (AccountPicker, journal line rows, report filters, ...).
 * Fetched once per mount from GET /accounts/options.
 */
export function useAccounts(tenantId: number | null = null): UseAccountsResult {
    const [accounts, setAccounts] = useState<AccountOption[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        fetchAccounts(tenantId)
            .then((data) => {
                if (!cancelled) setAccounts(data);
            })
            .catch(() => {
                if (!cancelled) setError('Could not load accounts.');
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [tenantId]);

    return { accounts, loading, error };
}
