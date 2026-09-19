import type { Variant } from '@/theme';
import type { ExpenseStatus, InvoiceStatus } from '@/types/finance';

export function invoiceStatusVariant(status: InvoiceStatus): Variant {
    return {
        draft: 'neutral',
        sent: 'info',
        paid: 'success',
        overdue: 'danger',
        void: 'neutral',
    }[status] as Variant;
}

export function expenseStatusVariant(status: ExpenseStatus): Variant {
    return {
        pending: 'warning',
        approved: 'info',
        paid: 'success',
    }[status] as Variant;
}

/**
 * Formats a Laravel timestamp (e.g. "2026-09-19T10:54:23.000000Z") as a
 * readable local date/time instead of dumping the raw ISO string.
 */
export function formatDateTime(value: string | null | undefined): string {
    if (!value) return '—';

    return new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

/**
 * Formats a Laravel "date"-cast field (e.g. "2026-09-30T00:00:00+03:00")
 * as a plain date, dropping the time/offset that field never meant to carry.
 */
export function formatDate(value: string | null | undefined): string {
    if (!value) return '—';

    return new Date(value).toLocaleDateString(undefined, { dateStyle: 'medium' });
}
