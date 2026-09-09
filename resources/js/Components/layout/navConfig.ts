import { BookOpen, LayoutDashboard, ListTree, Scale } from 'lucide-react';
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
 * with no items yet (Transactions, Tools, Settings) land as later phases
 * build out their routes.
 */
export const navConfig: NavConfigGroup[] = [
    {
        label: 'Overview',
        items: [{ label: 'Dashboard', routeName: 'dashboard', icon: LayoutDashboard }],
    },
    {
        label: 'Accounting',
        items: [
            { label: 'Chart of Accounts', routeName: 'accounts.index', icon: ListTree },
            { label: 'Journal Entries', routeName: 'journals.index', icon: BookOpen },
        ],
    },
    {
        label: 'Reports',
        items: [
            { label: 'Trial Balance', routeName: 'reports.trial-balance', icon: Scale },
            { label: 'General Ledger', routeName: 'reports.general-ledger', icon: BookOpen },
        ],
    },
];
