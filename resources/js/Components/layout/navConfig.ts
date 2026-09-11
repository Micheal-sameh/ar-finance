import { Boxes, BookOpen, ClipboardList, FileText, LandmarkIcon, ListTree, LayoutDashboard, PiggyBank, Receipt, ReceiptText, Scale, TrendingUp, Truck, Users, Users2 } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export interface NavConfigItem {
    label: string;
    routeName: string;
    icon: LucideIcon;
}

export interface NavConfigGroup {
    label: string;
    items: NavConfigItem[];
}

/**
 * Sidebar is entirely data-driven from this config — add a module's nav
 * entry here rather than hardcoding JSX in the Sidebar component. Groups
 * with no items yet (Tools, Settings) land as later phases build out
 * their routes.
 */
export const navConfig: NavConfigGroup[] = [
    {
        label: 'Overview',
        items: [{ label: 'Dashboard', routeName: 'dashboard', icon: LayoutDashboard }],
    },
    {
        label: 'Transactions',
        items: [
            { label: 'Invoices', routeName: 'invoices.index', icon: FileText },
            { label: 'Expenses', routeName: 'expenses.index', icon: Receipt },
            { label: 'Purchase Orders', routeName: 'purchase-orders.index', icon: ClipboardList },
            { label: 'Bills', routeName: 'bills.index', icon: ReceiptText },
            { label: 'Clients', routeName: 'clients.index', icon: Users },
            { label: 'Vendors', routeName: 'vendors.index', icon: Truck },
        ],
    },
    {
        label: 'Payroll',
        items: [
            { label: 'Employees', routeName: 'employees.index', icon: Users2 },
            { label: 'Payroll Runs', routeName: 'payroll-runs.index', icon: LandmarkIcon },
        ],
    },
    {
        label: 'Accounting',
        items: [
            { label: 'Chart of Accounts', routeName: 'accounts.index', icon: ListTree },
            { label: 'Journal Entries', routeName: 'journals.index', icon: BookOpen },
            { label: 'Cost Centers', routeName: 'cost-centers.index', icon: PiggyBank },
            { label: 'Fixed Assets', routeName: 'fixed-assets.index', icon: Boxes },
        ],
    },
    {
        label: 'Reports',
        items: [
            { label: 'Profit & Loss', routeName: 'reports.profit-and-loss', icon: TrendingUp },
            { label: 'Balance Sheet', routeName: 'reports.balance-sheet', icon: LandmarkIcon },
            { label: 'Trial Balance', routeName: 'reports.trial-balance', icon: Scale },
            { label: 'General Ledger', routeName: 'reports.general-ledger', icon: BookOpen },
        ],
    },
];
