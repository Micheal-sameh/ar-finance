import type { AccountOption, CostCenterOption, Paginated } from '@/types/finance';

export type BookingStatus = 'confirmed' | 'paid' | 'cancelled';
export type BookingRateType = 'hourly' | 'flat';
export type BookingRecurrence = 'none' | 'daily' | 'weekly' | 'monthly' | 'yearly';

export interface BookingType {
    id: number;
    name: string;
    rate_type: BookingRateType;
    price: string;
    /** This type's own revenue account on the item; null falls back to the item's. */
    account_id: number | null;
    /** This type's own cost/profit center on the item; null falls back to the item's. */
    cost_center_id: number | null;
}

export interface BookableItem {
    id: number;
    name: string;
    description: string | null;
    is_exclusive: boolean;
    rate_type: BookingRateType | null;
    price: string | null;
    account_id: number | null;
    cost_center_id: number | null;
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
    tenant_name?: string | null;
    name: string;
    is_exclusive: boolean;
    booking_types: BookingType[];
}

export interface Booking {
    id: number;
    bookable_item_id: number;
    user_id: number;
    starts_at: string;
    ends_at: string;
    price: string;
    status: BookingStatus;
    notes: string | null;
    paid_at: string | null;
}

/** Shape returned by GET /bookings/calendar, ready for FullCalendar. */
export interface BookingEvent {
    id: number;
    tenant_id?: number | null;
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
    discount_type: 'percent' | 'fixed' | null;
    discount_value: string | null;
    discount_amount: string;
    payment_method: 'cash' | 'bank_transfer' | 'pos' | null;
    payment_method_label: string | null;
    payment_reference: string | null;
    /** Owning tenant's name — only present when a Platform Admin is viewing the combined "All tenants" list. */
    tenant_name: string | null;
    can_cancel: boolean;
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
