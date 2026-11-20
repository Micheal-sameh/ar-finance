import type { Paginated } from '@/types/finance';

export type TreasuryTransferType = 'deposit' | 'withdrawal' | 'funding' | 'repayment' | 'move';
export type TreasuryTransferStatus = 'requested' | 'accepted' | 'rejected' | 'voided';

export interface TreasuryTransfer {
    id: number;
    type: TreasuryTransferType;
    type_label: string;
    status: TreasuryTransferStatus;
    status_label: string;
    tenant_id: number | null;
    /** Null for an internal cash/bank move. */
    tenant_name: string | null;
    transfer_date: string;
    requested_amount: string;
    accepted_amount: string | null;
    tenant_account: string | null;
    treasury_account_id: number | null;
    treasury_account: string | null;
    counter_account: string | null;
    reference: string | null;
    notes: string | null;
    reject_reason: string | null;
    requested_by: string | null;
    decided_by: string | null;
    decided_at: string | null;
    can_decide: boolean;
    can_void: boolean;
}

export interface TreasuryLocation {
    id: number;
    code: string;
    name: string;
    currency: string | null;
    balance: number;
}

export interface TreasuryPosition {
    tenant_id: number;
    tenant_name: string;
    held: number;
    advanced: number;
    tenant_due_from: number | null;
    tenant_due_to: number | null;
    matches: boolean;
}

export type TreasurySummary =
    | { mode: 'overview' }
    | { mode: 'treasury'; locations: TreasuryLocation[]; positions: TreasuryPosition[] }
    | { mode: 'tenant'; held: number; owed: number };

export interface TreasuryOptions {
    accounts: { id: number; label: string }[];
    defaultAccountId: number | null;
    locations: TreasuryLocation[];
    tenants: { id: number; name: string }[];
    types: { value: TreasuryTransferType; label: string }[];
    statuses: { value: TreasuryTransferStatus; label: string }[];
}

export interface TreasuryFilters {
    type: TreasuryTransferType | null;
    status: TreasuryTransferStatus | null;
    tenant_id: number | null;
    from: string | null;
    to: string | null;
    search: string | null;
}

export type TreasuryTransfersPage = Paginated<TreasuryTransfer>;
