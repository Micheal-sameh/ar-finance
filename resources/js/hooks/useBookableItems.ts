import { useEffect, useState } from 'react';
import api from '@/lib/api';
import type { BookableItemOption } from '@/types/booking';

interface UseBookableItemsResult {
    items: BookableItemOption[];
    loading: boolean;
    error: string | null;
}

/**
 * Active bookable items for the calendar's "new booking" picker. Fetched
 * once per mount from GET /bookable-items/options — same pattern as
 * useCostCenters().
 */
export function useBookableItems(enabled: boolean = true): UseBookableItemsResult {
    const [items, setItems] = useState<BookableItemOption[]>([]);
    const [loading, setLoading] = useState(enabled);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!enabled) {
            setItems([]);
            setLoading(false);
            return;
        }

        let cancelled = false;
        setLoading(true);

        api.get<BookableItemOption[]>(route('bookable-items.options'))
            .then((response) => {
                if (cancelled) return;

                if (!Array.isArray(response.data)) {
                    setError('Could not load bookable items.');
                    return;
                }

                setItems(response.data);
            })
            .catch(() => {
                if (!cancelled) setError('Could not load bookable items.');
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [enabled]);

    return { items, loading, error };
}
