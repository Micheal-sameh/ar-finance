export type AccountType = 'asset' | 'liability' | 'equity' | 'revenue' | 'expense';
export type NormalBalance = 'debit' | 'credit';
export type JournalSourceType = 'invoice' | 'expense' | 'payroll' | 'manual' | 'depreciation';

export interface Account {
    id: number;
    code: string;
    name: string;
    type: AccountType;
    normal_balance: NormalBalance;
    parent_id: number | null;
    is_active: boolean;
    parent?: Account | null;
}

/** Lightweight shape returned by /accounts/options for pickers. */
export interface AccountOption {
    id: number;
    code: string;
    name: string;
    type: AccountType;
    normal_balance: NormalBalance;
}

export interface JournalLine {
    id: number;
    journal_entry_id: number;
    account_id: number;
    account?: Account;
    debit: string;
    credit: string;
    cost_center_id: number | null;
    description: string | null;
}

export interface JournalEntry {
    id: number;
    date: string;
    description: string;
    reference: string | null;
    source_type: JournalSourceType;
    source_id: number | null;
    created_by: number;
    posted_at: string | null;
    lines: JournalLine[];
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

export interface TrialBalanceRow {
    account_id: number;
    code: string;
    name: string;
    type: AccountType;
    debit: number;
    credit: number;
}

export interface TrialBalanceReport {
    rows: TrialBalanceRow[];
    total_debit: number;
    total_credit: number;
    is_balanced: boolean;
}

export interface GeneralLedgerLine {
    date: string;
    description: string;
    reference: string | null;
    debit: number;
    credit: number;
    balance: number;
}

export interface GeneralLedgerReport {
    lines: GeneralLedgerLine[];
    ending_balance: number;
}
