import { ArrowLeftRight, Boxes, BookOpen, Building2, CircleDollarSign, ClipboardList, FileText, Globe, LandmarkIcon, ListTree, LayoutDashboard, PiggyBank, Receipt, ReceiptText, Percent, RefreshCw, Scale, ShieldCheck, TrendingUp, Truck, Users, Users2, Waves } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export interface NavConfigItem {
    label: string;
    routeName: string;
    icon: LucideIcon;
}

export interface NavConfigGroup {
    label: string;
    items: NavConfigItem[];
    /** Only shown to a Platform Admin (see HandleInertiaRequests' `platform` share). */
    platformOnly?: boolean;
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
            { label: 'P&C Centers', routeName: 'cost-centers.index', icon: PiggyBank },
            { label: 'Fixed Assets', routeName: 'fixed-assets.index', icon: Boxes },
            { label: 'Currency Revaluation', routeName: 'revaluation.index', icon: RefreshCw },
        ],
    },
    {
        label: 'Reports',
        items: [
            { label: 'Profit & Loss', routeName: 'reports.profit-and-loss', icon: TrendingUp },
            { label: 'Balance Sheet', routeName: 'reports.balance-sheet', icon: LandmarkIcon },
            { label: 'Cash Flow', routeName: 'reports.cash-flow', icon: Waves },
            { label: 'Trial Balance', routeName: 'reports.trial-balance', icon: Scale },
            { label: 'General Ledger', routeName: 'reports.general-ledger', icon: BookOpen },
            { label: 'VAT Return', routeName: 'reports.vat-return', icon: Percent },
            { label: 'AR/AP Aging', routeName: 'reports.aging', icon: CircleDollarSign },
        ],
    },
    {
        label: 'Tools',
        items: [
            { label: 'Bank Accounts', routeName: 'bank-accounts.index', icon: Building2 },
            { label: 'Exchange Rates', routeName: 'exchange-rates.index', icon: ArrowLeftRight },
        ],
    },
    {
        label: 'Settings',
        items: [{ label: 'Users', routeName: 'users.index', icon: ShieldCheck }],
    },
    {
        label: 'Platform',
        platformOnly: true,
        items: [
            { label: 'Dashboard', routeName: 'platform.dashboard', icon: Globe },
            { label: 'Tenants', routeName: 'platform.tenants.index', icon: Building2 },
        ],
    },
    {
        // Every tenant's own data, combined and read-only — see
        // TenantContext::isViewingAllTenants(). Switch into a tenant (nav
        // dropdown) to create/edit/delete instead.
        label: 'All Tenants',
        platformOnly: true,
        items: [
            { label: 'Chart of Accounts', routeName: 'accounts.index', icon: ListTree },
            { label: 'Journal Entries', routeName: 'journals.index', icon: BookOpen },
            { label: 'General Ledger', routeName: 'reports.general-ledger', icon: BookOpen },
            { label: 'P&C Centers', routeName: 'cost-centers.index', icon: PiggyBank },
            { label: 'Fixed Assets', routeName: 'fixed-assets.index', icon: Boxes },
            { label: 'Invoices', routeName: 'invoices.index', icon: FileText },
            { label: 'Expenses', routeName: 'expenses.index', icon: Receipt },
            { label: 'Bills', routeName: 'bills.index', icon: ReceiptText },
            { label: 'Clients', routeName: 'clients.index', icon: Users },
            { label: 'Vendors', routeName: 'vendors.index', icon: Truck },
            { label: 'Employees', routeName: 'employees.index', icon: Users2 },
            { label: 'Bank Accounts', routeName: 'bank-accounts.index', icon: Building2 },
            { label: 'Exchange Rates', routeName: 'exchange-rates.index', icon: ArrowLeftRight },
            { label: 'Users', routeName: 'users.index', icon: ShieldCheck },
        ],
    },
    {
        label: 'Reports',
        platformOnly: true,
        items: [
            { label: 'Profit & Loss', routeName: 'platform.reports.profit-and-loss', icon: TrendingUp },
            { label: 'Balance Sheet', routeName: 'platform.reports.balance-sheet', icon: LandmarkIcon },
            { label: 'Cash Flow', routeName: 'platform.reports.cash-flow', icon: Waves },
            { label: 'Trial Balance', routeName: 'platform.reports.trial-balance', icon: Scale },
            { label: 'VAT Return', routeName: 'platform.reports.vat-return', icon: Percent },
            { label: 'AR/AP Aging', routeName: 'platform.reports.aging', icon: CircleDollarSign },
        ],
    },
];
