import type { Paginated } from '@/types/finance';

export type DonationStatus = 'received' | 'cancelled';
export type DonationPaymentMethod = 'cash' | 'bank_transfer' | 'pos';

export interface Donation {
    id: number;
    receipt_number: string;
    donated_at: string;
    donor_id: number | null;
    /** Null for an anonymous donation. */
    donor_name: string | null;
    cost_center_id: number;
    cost_center_name: string | null;
    amount: string;
    currency: string;
    exchange_rate: string;
    base_amount: string;
    payment_method: DonationPaymentMethod;
    payment_method_label: string;
    payment_reference: string | null;
    status: DonationStatus;
    status_label: string;
    notes: string | null;
    created_by_name: string | null;
    created_at: string | null;
    tenant_name: string | null;
    can_cancel: boolean;
    can_print: boolean;
}

export interface Donor {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    notes: string | null;
    donations_count: number;
}

export interface DonationOptions {
    donors: { id: number; name: string; phone: string | null }[];
    costCenters: { id: number; name: string }[];
    currencies: { code: string; name: string }[];
    baseCurrency: string;
    paymentMethods: { value: DonationPaymentMethod; label: string }[];
    creators: { id: number; name: string }[];
}

export interface DonationFilters {
    search: string | null;
    from: string | null;
    to: string | null;
    cost_center_id: number | null;
    currency: string | null;
    payment_method: DonationPaymentMethod | null;
    status: DonationStatus | null;
    created_by: number | null;
}

export type DonationsPage = Paginated<Donation>;
export type DonorsPage = Paginated<Donor>;
