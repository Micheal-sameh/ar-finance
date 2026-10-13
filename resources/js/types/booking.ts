import type { AccountOption, CostCenterOption, Paginated } from '@/types/finance';

export type BookingStatus = 'confirmed' | 'paid' | 'cancelled';
export type BookingRateType = 'hourly' | 'flat';
export type BookingRecurrence = 'none' | 'daily' | 'weekly' | 'monthly' | 'yearly';

export interface BookingType {
    id: number;
    name: string;
    rate_type: BookingRateType;
    price: string;
}

export interface BookableItem {
    id: number;
    name: string;
    description: string | null;
    is_exclusive: boolean;
    rate_type: BookingRateType | null;
    price: string | null;
    deposit_percent: string | null;
    account_id: number;
    deferred_revenue_account_id: number | null;
    deposit_payment_account_id: number | null;
    cost_center_id: number;
    is_active: boolean;
    booking_types?: BookingType[];
    account?: AccountOption | null;
    cost_center?: CostCenterOption | null;
    /** Owning tenant's name — only present when a Platform Admin is viewing the combined "All tenants" list. */
    tenant_name?: string | null;
}

/** Lightweight shape returned by /bookable-items/options for the calendar's item picker. */
export interface BookableItemOption {
    id: number;
    name: string;
    is_exclusive: boolean;
    deposit_percent: string | null;
    booking_types: BookingType[];
}

export interface Booking {
    id: number;
    bookable_item_id: number;
    user_id: number;
    starts_at: string;
    ends_at: string;
    price: string;
    deposit_amount: string | null;
    balance_paid: string;
    deposit_paid_at: string | null;
    payment_due_at: string | null;
    status: BookingStatus;
    notes: string | null;
    paid_at: string | null;
}

/** Shape returned by GET /bookings/calendar, ready for FullCalendar. */
export interface BookingEvent {
    id: number;
    title: string;
    start: string;
    end: string;
    status: BookingStatus;
    bookable_item_id: number;
    booking_type_name: string | null;
    recurrence: BookingRecurrence;
    recurrence_until: string | null;
    is_exclusive: boolean;
    price: string;
    accepts_deposits: boolean;
    deposit_percent: string | null;
    deposit_amount: string | null;
    balance_paid: string | null;
    deposit_paid_at: string | null;
    payment_due_at: string | null;
    balance_due: number;
    is_overdue: boolean;
    /** Owning tenant's name — only present when a Platform Admin is viewing the combined "All tenants" list. */
    tenant_name: string | null;
    can_cancel: boolean;
    can_mark_paid: boolean;
    can_pay_deposit: boolean;
    is_mine: boolean;
}

export type BookableItemsPage = Paginated<BookableItem>;

/** A row on the Booking Types page: the reusable catalog; prices live on each bookable item. */
export interface BookingTypeSummary {
    id: number;
    name: string;
    bookable_items_count: number;
    /** Owning tenant name, only present when a Platform Admin views the combined "All tenants" list. */
    tenant_name: string | null;
}
