import { useEffect, useState } from 'react';
import api from '@/lib/api';
import type { CostCenterOption } from '@/types/finance';

interface UseCostCentersResult {
    costCenters: CostCenterOption[];
    loading: boolean;
    error: string | null;
}

/**
 * Single shared source of active cost centers for any picker in the app
 * (JournalLineEditor rows, expense tagging, ...). Fetched once per mount
 * from GET /cost-centers/options.
 */
export function useCostCenters(): UseCostCentersResult {
    const [costCenters, setCostCenters] = useState<CostCenterOption[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        api.get<CostCenterOption[]>(route('cost-centers.options'))
            .then((response) => {
                if (!cancelled) setCostCenters(response.data);
            })
            .catch(() => {
                if (!cancelled) setError('Could not load cost centers.');
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, []);

    return { costCenters, loading, error };
}
