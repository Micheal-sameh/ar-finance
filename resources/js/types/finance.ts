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

export type PurchaseOrderStatus = 'draft' | 'sent' | 'closed' | 'cancelled';
export type BillStatus = 'draft' | 'approved' | 'paid';

export interface PurchaseOrderLine {
    id: number;
    description: string;
    quantity: string;
    unit_price: string;
    account_id: number;
    account?: Account;
}

export interface PurchaseOrder {
    id: number;
    vendor_id: number;
    vendor?: Vendor;
    po_number: string;
    order_date: string;
    expected_date: string | null;
    status: PurchaseOrderStatus;
    lines: PurchaseOrderLine[];
}

export interface BillLine {
    id: number;
    description: string;
    quantity: string;
    unit_price: string;
    account_id: number;
    account?: Account;
}

export interface Bill {
    id: number;
    vendor_id: number;
    vendor?: Vendor;
    purchase_order_id: number | null;
    purchase_order?: PurchaseOrder | null;
    bill_number: string;
    bill_date: string;
    due_date: string;
    status: BillStatus;
    payable_account_id: number;
    payable_account?: Account;
    cost_center_id: number | null;
    paid_at: string | null;
    lines: BillLine[];
}

export type DepreciationMethod = 'straight_line';

export interface FixedAsset {
    id: number;
    name: string;
    purchase_date: string;
    cost: string;
    salvage_value: string;
    useful_life_years: number;
    depreciation_method: DepreciationMethod;
    asset_account_id: number;
    asset_account?: Account;
    depreciation_account_id: number;
    depreciation_account?: Account;
    accumulated_depreciation_account_id: number;
    accumulated_depreciation_account?: Account;
    accumulated_depreciation: string;
}

export interface DepreciationScheduleRow {
    month: string;
    amount: number;
    cumulative: number;
    book_value: number;
    posted: boolean;
}

export interface Employee {
    id: number;
    name: string;
    email: string | null;
    job_title: string | null;
    salary: string;
    hire_date: string;
    is_active: boolean;
}

export type PayrollRunStatus = 'draft' | 'approved' | 'paid';

export interface Payslip {
    id: number;
    employee_id: number;
    employee?: Employee;
    gross_pay: string;
    deductions: string;
    net_pay: string;
}

export interface PayrollRun {
    id: number;
    period_start: string;
    period_end: string;
    pay_date: string;
    status: PayrollRunStatus;
    expense_account_id: number;
    expense_account?: Account;
    payable_account_id: number;
    payable_account?: Account;
    deductions_payable_account_id: number | null;
    deductions_payable_account?: Account | null;
    paid_at: string | null;
    payslips: Payslip[];
}

export interface BankAccount {
    id: number;
    name: string;
    account_id: number;
    account?: Account;
    bank_name: string | null;
    account_number: string | null;
    currency: string;
}

export interface BankTransactionMatchedLine {
    id: number;
    debit: string;
    credit: string;
    description: string | null;
    journal_entry?: { id: number; date: string; description: string };
}

export interface BankTransaction {
    id: number;
    bank_account_id: number;
    date: string;
    description: string;
    amount: string;
    matched_journal_line_id: number | null;
    matched_journal_line?: BankTransactionMatchedLine | null;
}

export interface UnmatchedJournalLine {
    id: number;
    debit: string;
    credit: string;
    description: string | null;
    journal_entry?: { id: number; date: string; description: string };
}
