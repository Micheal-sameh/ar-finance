import type { Variant } from '@/theme';
import type { Bill, BillStatus, ExpenseStatus, InvoiceStatus } from '@/types/finance';

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

/** Tax-inclusive bill total. */
export function billTotal(bill: Bill): number {
    return bill.lines.reduce(
        (sum, line) => sum + parseFloat(line.quantity) * parseFloat(line.unit_price) * (1 + parseFloat(line.tax_rate) / 100),
        0,
    );
}

export function billAmountPaid(bill: Bill): number {
    return (bill.payments ?? []).reduce((sum, payment) => sum + parseFloat(payment.amount), 0);
}

export function billBalanceDue(bill: Bill): number {
    return Math.round((billTotal(bill) - billAmountPaid(bill)) * 100) / 100;
}

/** Posted to the ledger and still owing something to the vendor. */
export function billIsPayable(bill: Bill): boolean {
    return bill.status === 'approved' || bill.status === 'partially_paid';
}

export function billStatusLabel(status: BillStatus): string {
    return { draft: 'Draft', approved: 'Approved', partially_paid: 'Partially paid', paid: 'Paid' }[status];
}

export function billStatusVariant(status: BillStatus): Variant {
    return { draft: 'neutral', approved: 'warning', partially_paid: 'info', paid: 'success' }[status] as Variant;
}

