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
