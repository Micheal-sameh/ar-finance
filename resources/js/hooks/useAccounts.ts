import { useEffect, useState } from 'react';
import api from '@/lib/api';
import type { AccountOption } from '@/types/finance';

interface UseAccountsResult {
    accounts: AccountOption[];
    loading: boolean;
    error: string | null;
}

/**
 * Single shared source of the active chart of accounts for any picker in
 * the app (AccountPicker, journal line rows, report filters, ...).
 * Fetched once per mount from GET /accounts/options.
 */
export function useAccounts(): UseAccountsResult {
    const [accounts, setAccounts] = useState<AccountOption[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        api.get<AccountOption[]>(route('accounts.options'))
            .then((response) => {
                if (!cancelled) setAccounts(response.data);
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
    }, []);

    return { accounts, loading, error };
}
