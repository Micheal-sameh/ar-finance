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

export type InvoiceStatus = 'draft' | 'sent' | 'paid' | 'overdue' | 'void';
export type ExpenseStatus = 'pending' | 'approved' | 'paid';

export interface Client {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    tax_number: string | null;
    address: string | null;
    currency: string;
}

export interface Vendor {
    id: number;
    name: string;
    email: string | null;
    tax_number: string | null;
    payment_terms: string | null;
}

export interface InvoiceLine {
    id: number;
    description: string;
    quantity: string;
    unit_price: string;
    tax_rate: string;
    account_id: number;
    account?: Account;
}

export interface Invoice {
    id: number;
    client_id: number;
    client?: Client;
    invoice_number: string;
    issue_date: string;
    due_date: string;
    status: InvoiceStatus;
    currency: string;
    exchange_rate: string;
    receivable_account_id: number;
    receivable_account?: Account;
    paid_at: string | null;
    lines: InvoiceLine[];
}

export interface Expense {
    id: number;
    description: string;
    account_id: number;
    account?: Account;
    amount: string;
    date: string;
    vendor_id: number | null;
    vendor?: Vendor | null;
    cost_center_id: number | null;
    payable_account_id: number;
    payable_account?: Account;
    receipt_path: string | null;
    status: ExpenseStatus;
    paid_at: string | null;
}

export interface ProfitLossRow {
    account_id: number;
    code: string;
    name: string;
    current: number;
    prior: number;
}

export interface ProfitAndLossReport {
    from: string;
    to: string;
    compare_from: string | null;
    compare_to: string | null;
    revenue: ProfitLossRow[];
    expenses: ProfitLossRow[];
    total_revenue: { current: number; prior: number };
    total_expenses: { current: number; prior: number };
    net_profit: { current: number; prior: number };
}

export interface BalanceSheetRow {
    account_id: number;
    code: string;
    name: string;
    balance: number;
}

export interface BalanceSheetReport {
    as_of: string;
    assets: BalanceSheetRow[];
    liabilities: BalanceSheetRow[];
    equity: BalanceSheetRow[];
    total_assets: number;
    total_liabilities: number;
    total_equity: number;
    is_balanced: boolean;
}

export type CostCenterType = 'cost' | 'profit';

export interface CostCenter {
    id: number;
    name: string;
    type: CostCenterType;
    budget: string | null;
    parent_id: number | null;
    is_active: boolean;
    parent?: CostCenter | null;
}

/** Lightweight shape returned by /cost-centers/options for pickers. */
export interface CostCenterOption {
    id: number;
    name: string;
    type: CostCenterType;
}

export interface CostCenterSummaryRow {
    cost_center_id: number;
    name: string;
    type: CostCenterType;
    budget: number | null;
    spent: number;
    revenue: number;
    net: number;
    utilization_percent: number | null;
}
