import { ArrowLeftRight, Boxes, BookOpen, BookText, Building2, Calendar, CircleDollarSign, ClipboardList, FileText, Globe, LandmarkIcon, ListTree, LayoutDashboard, PiggyBank, Receipt, ReceiptText, Percent, RefreshCw, Scale, ShieldCheck, Tags, TrendingUp, Truck, Users, Users2, Waves } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export interface NavConfigItem {
    label: string;
    routeName: string;
    icon: LucideIcon;
    /** Permission required to see this link, matching what its controller authorizes — omit only for links every authenticated user may see. */
    permission?: string;
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
        items: [{ label: 'Dashboard', routeName: 'dashboard', icon: LayoutDashboard, permission: 'accounts.view' }],
    },
    {
        label: 'Transactions',
        items: [
            { label: 'Invoices', routeName: 'invoices.index', icon: FileText, permission: 'invoices.view' },
            { label: 'Expenses', routeName: 'expenses.index', icon: Receipt, permission: 'expenses.view' },
            { label: 'Purchase Orders', routeName: 'purchase-orders.index', icon: ClipboardList, permission: 'purchase_orders.view' },
            { label: 'Bills', routeName: 'bills.index', icon: ReceiptText, permission: 'bills.view' },
            { label: 'Clients', routeName: 'clients.index', icon: Users, permission: 'clients.view' },
            { label: 'Vendors', routeName: 'vendors.index', icon: Truck, permission: 'vendors.view' },
        ],
    },
    {
        label: 'Booking',
        items: [
            { label: 'Calendar', routeName: 'bookings.index', icon: Calendar, permission: 'bookings.view' },
            { label: 'Bookable Items', routeName: 'bookable-items.index', icon: ClipboardList, permission: 'bookable_items.view' },
            { label: 'Booking Types', routeName: 'booking-types.index', icon: Tags, permission: 'bookable_items.view' },
        ],
    },
    {
        label: 'Payroll',
        items: [
            { label: 'Employees', routeName: 'employees.index', icon: Users2, permission: 'employees.view' },
            { label: 'Payroll Runs', routeName: 'payroll-runs.index', icon: LandmarkIcon, permission: 'payroll.view' },
        ],
    },
    {
        label: 'Accounting',
        items: [
            { label: 'Chart of Accounts', routeName: 'accounts.index', icon: ListTree, permission: 'accounts.view' },
            { label: 'Journal Entries', routeName: 'journals.index', icon: BookOpen, permission: 'journals.view' },
            { label: 'P&C Centers', routeName: 'cost-centers.index', icon: PiggyBank, permission: 'cost_centers.view' },
            { label: 'Fixed Assets', routeName: 'fixed-assets.index', icon: Boxes, permission: 'fixed_assets.view' },
            { label: 'Currency Revaluation', routeName: 'revaluation.index', icon: RefreshCw, permission: 'invoices.view' },
        ],
    },
    {
        label: 'Reports',
        items: [
            { label: 'Profit & Loss', routeName: 'reports.profit-and-loss', icon: TrendingUp, permission: 'accounts.view' },
            { label: 'Balance Sheet', routeName: 'reports.balance-sheet', icon: LandmarkIcon, permission: 'accounts.view' },
            { label: 'Cash Flow', routeName: 'reports.cash-flow', icon: Waves, permission: 'accounts.view' },
            { label: 'Trial Balance', routeName: 'reports.trial-balance', icon: Scale, permission: 'accounts.view' },
            { label: 'General Ledger', routeName: 'reports.general-ledger', icon: BookText, permission: 'accounts.view' },
            { label: 'VAT Return', routeName: 'reports.vat-return', icon: Percent, permission: 'accounts.view' },
            { label: 'AR/AP Aging', routeName: 'reports.aging', icon: CircleDollarSign, permission: 'accounts.view' },
        ],
    },
    {
        label: 'Tools',
        items: [
            { label: 'Bank Accounts', routeName: 'bank-accounts.index', icon: Building2, permission: 'bank_accounts.view' },
            { label: 'Exchange Rates', routeName: 'exchange-rates.index', icon: ArrowLeftRight, permission: 'exchange_rates.view' },
        ],
    },
    {
        label: 'Settings',
        items: [{ label: 'Users', routeName: 'users.index', icon: ShieldCheck, permission: 'users.view' }],
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
            { label: 'General Ledger', routeName: 'reports.general-ledger', icon: BookText },
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
            { label: 'Bookable Items', routeName: 'bookable-items.index', icon: ClipboardList },
            { label: 'Booking Types', routeName: 'booking-types.index', icon: Tags },
            { label: 'Calendar', routeName: 'bookings.index', icon: Calendar },
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

/**
 * The nav groups a user may see: platform groups only while a Platform
 * Admin views all tenants, and only items whose permission the user holds
 * (the server would 403 on the rest, so they're hidden rather than left to
 * click through into a dead end).
 */
export function visibleNavGroups(permissions: string[], isPlatformAdminViewingAllTenants: boolean): NavConfigGroup[] {
    const held = new Set(permissions);

    return navConfig
        .filter((group) => Boolean(group.platformOnly) === isPlatformAdminViewingAllTenants)
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => !item.permission || held.has(item.permission)),
        }))
        .filter((group) => group.items.length > 0);
}
