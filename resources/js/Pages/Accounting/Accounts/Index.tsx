import { Head, router, useForm } from '@inertiajs/react';
import { Building2, ChevronDown, ChevronRight, ListTree, Pencil, Plus, Trash2, Upload } from 'lucide-react';
import { Fragment, FormEvent, useMemo, useState } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { ServiceField } from '@/Components/layout/ServiceField';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { useConfirm } from '@/Components/ui/ConfirmProvider';
import { EmptyState } from '@/Components/ui/EmptyState';
import { ExportButton } from '@/Components/ui/ExportButton';
import { ImportModal } from '@/Components/ui/ImportModal';
import { FilterPanel } from '@/Components/ui/FilterPanel';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { Table } from '@/Components/ui/Table';
import type { Account, AccountType, NormalBalance } from '@/types/finance';
import { InfoTip } from '@/Components/ui/InfoTip';

interface CurrencyOption {
    code: string;
    name: string;
}

/** One tenant's own instance of a merged (same-code) account — see MergedAccount. */
interface MergedAccountTenant {
    tenant_id: number;
    tenant_name: string;
    account_id: number;
    parent_id: number | null;
    balance: number;
    is_active: boolean;
}

/**
 * A Platform Admin viewing "All tenants" sees one row per account *code*
 * instead of one row per tenant's own account row — every tenant starts
 * from the same seeded chart of accounts, so merging by code avoids
 * showing e.g. "1000 Assets" once per tenant. `tenants` carries each
 * tenant's own account/balance for the click-to-expand breakdown.
 */
interface MergedAccount {
    code: string;
    name: string;
    type: AccountType;
    normal_balance: NormalBalance;
    currency: string;
    parent_code: string | null;
    is_active: boolean;
    is_deletable: boolean;
    balance: number;
    tenants: MergedAccountTenant[];
}

interface Props {
    accounts: Account[] | MergedAccount[];
    filters: { type?: string; is_active?: string; search?: string };
    baseCurrency: string;
    currencyOptions: CurrencyOption[];
    viewingAllTenants: boolean;
    accountBudgets: Record<number, AccountBudget>;
}

/** Year-to-date actual against the budget for the same stretch of the year, for revenue and expense accounts. */
interface AccountBudget {
    budget: number;
    actual: number;
    year_budget: number;
}

function AccountBudgetCell({ budget }: { budget?: AccountBudget }) {
    if (!budget || budget.budget === 0) {
        return <span style={{ color: 'var(--af-label)' }}>—</span>;
    }

    const percent = Math.round((budget.actual / budget.budget) * 100);
    const over = percent > 100;

    return (
        <span title={`Year budget ${budget.year_budget.toLocaleString()}`}>
            <MoneyDisplay amount={budget.actual} /> of <MoneyDisplay amount={budget.budget} />{' '}
            <span style={{ color: over ? 'var(--af-danger)' : 'var(--af-label)', fontWeight: over ? 600 : 400 }}>({percent}%)</span>
        </span>
    );
}

interface AccountNode extends Account {
    children: AccountNode[];
}

/**
 * Nests the flat account list into per-type forests using parent_id. Since
 * a child's type is always validated to match its parent's, every node in
 * a root's subtree shares that root's type.
 */
function buildForest(accounts: Account[]): AccountNode[] {
    const byId = new Map<number, AccountNode>();
    accounts.forEach((account) => byId.set(account.id, { ...account, children: [] }));

    const roots: AccountNode[] = [];
    byId.forEach((node) => {
        const parent = node.parent_id ? byId.get(node.parent_id) : undefined;
        if (parent) {
            parent.children.push(node);
        } else {
            roots.push(node);
        }
    });

    const sortByCode = (nodes: AccountNode[]) => {
        nodes.sort((a, b) => a.code.localeCompare(b.code));
        nodes.forEach((node) => sortByCode(node.children));
    };
    sortByCode(roots);

    return roots;
}

// Each type's own normal-balance direction — matches AccountType::defaultNormalBalance() on the backend.
const TYPE_DEFAULT_NORMAL_BALANCE: Record<AccountType, 'debit' | 'credit'> = {
    asset: 'debit',
    liability: 'credit',
    equity: 'credit',
    revenue: 'credit',
    expense: 'debit',
};

/**
 * A node's own balance, sign-corrected to its type's normal-balance
 * direction rather than its own — so a contra account (e.g. Accumulated
 * Depreciation: type asset, but credit-normal) contributes negatively
 * instead of inflating the total. Without this, summing it in with
 * regular debit-normal assets would overstate a collapsed parent's total.
 */
function typeSignedBalance(node: AccountNode): number {
    const own = node.balance ?? 0;
    return node.normal_balance === TYPE_DEFAULT_NORMAL_BALANCE[node.type] ? own : -own;
}

/** Sum of a node's own balance plus every descendant's — shown in place of a collapsed parent's own (often zero) balance so nothing hidden goes unaccounted for. */
function subtreeBalance(node: AccountNode): number {
    return node.children.reduce((sum, child) => sum + subtreeBalance(child), typeSignedBalance(node));
}

const ACCOUNT_TYPES: { value: AccountType; label: string }[] = [
    { value: 'asset', label: 'Asset' },
    { value: 'liability', label: 'Liability' },
    { value: 'equity', label: 'Equity' },
    { value: 'revenue', label: 'Revenue' },
    { value: 'expense', label: 'Expense' },
];

// Chart-of-accounts numbering convention: the code's first digit says its type.
const TYPE_BY_CODE_PREFIX: Record<string, AccountType> = {
    '1': 'asset',
    '2': 'liability',
    '3': 'equity',
    '4': 'revenue',
    '5': 'expense',
};

interface AccountTreeRowsProps {
    node: AccountNode;
    depth: number;
    collapsed: Set<number>;
    onToggle: (id: number) => void;
    onEdit: (account: Account) => void;
    onDelete: (account: Account) => void;
    baseCurrency: string;
    budgets: Record<number, AccountBudget>;
}

function AccountTreeRows({ node, depth, collapsed, onToggle, onEdit, onDelete, baseCurrency, budgets }: AccountTreeRowsProps) {
    const hasBudgets = Object.keys(budgets).length > 0;
    const hasChildren = node.children.length > 0;
    const isExpanded = !collapsed.has(node.id);

    return (
        <>
            <Table.Row>
                <Table.Cell className="ps-3">{node.code}</Table.Cell>
                <Table.Cell>
                    <div className="d-flex align-items-center gap-1" style={{ paddingLeft: depth * 20 }}>
                        {hasChildren ? (
                            <button
                                type="button"
                                className="btn btn-sm p-0 d-flex align-items-center justify-content-center"
                                style={{ color: 'var(--af-label)', width: '18px', height: '18px' }}
                                onClick={() => onToggle(node.id)}
                                aria-label={isExpanded ? 'Collapse' : 'Expand'}
                            >
                                {isExpanded ? <ChevronDown size={14} /> : <ChevronRight size={14} />}
                            </button>
                        ) : (
                            <span style={{ width: '18px', display: 'inline-block' }} />
                        )}
                        <span>{node.name}</span>
                    </div>
                </Table.Cell>
                <Table.Cell style={{ textTransform: 'capitalize' }}>{node.normal_balance}</Table.Cell>
                <Table.Cell>{node.currency}</Table.Cell>
                <Table.Cell className="text-end">
                    <MoneyDisplay
                        amount={hasChildren && !isExpanded ? subtreeBalance(node) : node.balance ?? 0}
                        currency={baseCurrency}
                    />
                </Table.Cell>
                {hasBudgets && (
                    <Table.Cell className="text-end">
                        <AccountBudgetCell budget={budgets[node.id]} />
                    </Table.Cell>
                )}
                <Table.Cell>
                    <Badge variant={node.is_active ? 'success' : 'neutral'}>{node.is_active ? 'Active' : 'Inactive'}</Badge>
                </Table.Cell>
                <Table.Cell className="text-end pe-3">
                    <div className="d-flex justify-content-end gap-1">
                        <button
                            type="button"
                            className="btn btn-sm p-1"
                            style={{ color: 'var(--af-label)' }}
                            onClick={() => onEdit(node)}
                            aria-label="Edit"
                        >
                            <Pencil size={15} />
                        </button>
                        {node.is_deletable && (
                            <button
                                type="button"
                                className="btn btn-sm p-1"
                                style={{ color: 'var(--af-danger)' }}
                                onClick={() => onDelete(node)}
                                aria-label="Delete"
                            >
                                <Trash2 size={15} />
                            </button>
                        )}
                    </div>
                </Table.Cell>
            </Table.Row>
            {hasChildren &&
                isExpanded &&
                node.children.map((child) => (
                    <AccountTreeRows
                        key={child.id}
                        node={child}
                        depth={depth + 1}
                        collapsed={collapsed}
                        onToggle={onToggle}
                        onEdit={onEdit}
                        onDelete={onDelete}
                        baseCurrency={baseCurrency}
                        budgets={budgets}
                    />
                ))}
        </>
    );
}

interface MergedAccountNode extends MergedAccount {
    children: MergedAccountNode[];
}

/** Same nesting idea as buildForest(), but keyed by code since merged rows have no shared numeric id across tenants. */
function buildMergedForest(accounts: MergedAccount[]): MergedAccountNode[] {
    const byCode = new Map<string, MergedAccountNode>();
    accounts.forEach((account) => byCode.set(account.code, { ...account, children: [] }));

    const roots: MergedAccountNode[] = [];
    byCode.forEach((node) => {
        const parent = node.parent_code ? byCode.get(node.parent_code) : undefined;
        if (parent) {
            parent.children.push(node);
        } else {
            roots.push(node);
        }
    });

    const sortByCode = (nodes: MergedAccountNode[]) => {
        nodes.sort((a, b) => a.code.localeCompare(b.code));
        nodes.forEach((node) => sortByCode(node.children));
    };
    sortByCode(roots);

    return roots;
}

function mergedTypeSignedBalance(node: MergedAccountNode): number {
    const own = node.balance ?? 0;
    return node.normal_balance === TYPE_DEFAULT_NORMAL_BALANCE[node.type] ? own : -own;
}

function mergedSubtreeBalance(node: MergedAccountNode): number {
    return node.children.reduce((sum, child) => sum + mergedSubtreeBalance(child), mergedTypeSignedBalance(node));
}

interface MergedAccountTreeRowsProps {
    node: MergedAccountNode;
    depth: number;
    collapsedCodes: Set<string>;
    onToggle: (code: string) => void;
    expandedTenantsFor: Set<string>;
    onToggleTenants: (code: string) => void;
    onEditTenantAccount: (node: MergedAccountNode, tenant: MergedAccountTenant) => void;
    baseCurrency: string;
}

/**
 * Mirrors AccountTreeRows' code-hierarchy expand/collapse, plus a second,
 * independent toggle (the tenant-count pill) that reveals each tenant's
 * own account beneath this merged row — see MergedAccount.
 */
function MergedAccountTreeRows({
    node,
    depth,
    collapsedCodes,
    onToggle,
    expandedTenantsFor,
    onToggleTenants,
    onEditTenantAccount,
    baseCurrency,
}: MergedAccountTreeRowsProps) {
    const confirm = useConfirm();

    async function removeTenantAccount(tenant: MergedAccountTenant) {
        if (await confirm(`Delete ${node.code} ${node.name} from ${tenant.tenant_name}?`, { variant: 'danger', confirmLabel: 'Delete' })) {
            router.delete(route('accounts.destroy', tenant.account_id));
        }
    }

    const hasChildren = node.children.length > 0;
    const isExpanded = !collapsedCodes.has(node.code);
    const tenantsExpanded = expandedTenantsFor.has(node.code);

    return (
        <>
            <Table.Row>
                <Table.Cell className="ps-3">{node.code}</Table.Cell>
                <Table.Cell>
                    <div className="d-flex align-items-center gap-1" style={{ paddingLeft: depth * 20 }}>
                        {hasChildren ? (
                            <button
                                type="button"
                                className="btn btn-sm p-0 d-flex align-items-center justify-content-center"
                                style={{ color: 'var(--af-label)', width: '18px', height: '18px' }}
                                onClick={() => onToggle(node.code)}
                                aria-label={isExpanded ? 'Collapse' : 'Expand'}
                            >
                                {isExpanded ? <ChevronDown size={14} /> : <ChevronRight size={14} />}
                            </button>
                        ) : (
                            <span style={{ width: '18px', display: 'inline-block' }} />
                        )}
                        <span>{node.name}</span>
                    </div>
                </Table.Cell>
                <Table.Cell style={{ textTransform: 'capitalize' }}>{node.normal_balance}</Table.Cell>
                <Table.Cell>{node.currency}</Table.Cell>
                <Table.Cell>
                    <button
                        type="button"
                        className="btn btn-sm d-inline-flex align-items-center gap-1 p-0"
                        style={{ color: 'var(--af-primary)', fontSize: '13px' }}
                        onClick={() => onToggleTenants(node.code)}
                    >
                        <Building2 size={13} />
                        {node.tenants.length} tenant{node.tenants.length === 1 ? '' : 's'}
                        {tenantsExpanded ? <ChevronDown size={13} /> : <ChevronRight size={13} />}
                    </button>
                </Table.Cell>
                <Table.Cell className="text-end">
                    <MoneyDisplay
                        amount={hasChildren && !isExpanded ? mergedSubtreeBalance(node) : node.balance ?? 0}
                        currency={baseCurrency}
                    />
                </Table.Cell>
                <Table.Cell>
                    <Badge variant={node.is_active ? 'success' : 'neutral'}>{node.is_active ? 'Active' : 'Inactive'}</Badge>
                </Table.Cell>
            </Table.Row>
            {tenantsExpanded &&
                node.tenants.map((tenant) => (
                    <Table.Row key={tenant.account_id} style={{ backgroundColor: 'var(--af-surface-alt, rgba(0,0,0,0.02))' }}>
                        <Table.Cell className="ps-3" />
                        <Table.Cell>
                            <div className="d-flex align-items-center gap-1" style={{ paddingLeft: (depth + 1) * 20, fontSize: '13px', color: 'var(--af-label)' }}>
                                {tenant.tenant_name}
                            </div>
                        </Table.Cell>
                        <Table.Cell />
                        <Table.Cell />
                        <Table.Cell />
                        <Table.Cell className="text-end">
                            <MoneyDisplay amount={tenant.balance} currency={baseCurrency} />
                        </Table.Cell>
                        <Table.Cell>
                            <Badge variant={tenant.is_active ? 'success' : 'neutral'}>{tenant.is_active ? 'Active' : 'Inactive'}</Badge>
                            <button type="button" className="btn btn-sm p-1 ms-2" style={{ color: 'var(--af-label)' }} onClick={() => onEditTenantAccount(node, tenant)} aria-label="Edit">
                                <Pencil size={14} />
                            </button>
                            <button type="button" className="btn btn-sm p-1 ms-2" style={{ color: 'var(--af-danger)' }} onClick={() => removeTenantAccount(tenant)} aria-label="Delete">
                                <Trash2 size={14} />
                            </button>
                        </Table.Cell>
                    </Table.Row>
                ))}
            {hasChildren &&
                isExpanded &&
                node.children.map((child) => (
                    <MergedAccountTreeRows
                        key={child.code}
                        node={child}
                        depth={depth + 1}
                        collapsedCodes={collapsedCodes}
                        onToggle={onToggle}
                        expandedTenantsFor={expandedTenantsFor}
                        onToggleTenants={onToggleTenants}
                        onEditTenantAccount={onEditTenantAccount}
                        baseCurrency={baseCurrency}
                    />
                ))}
        </>
    );
}

function typeBadgeVariant(type: AccountType) {
    return { asset: 'primary', liability: 'warning', equity: 'info', revenue: 'success', expense: 'danger' }[type] as
        | 'primary'
        | 'warning'
        | 'info'
        | 'success'
        | 'danger';
}

export default function AccountsIndex({ accounts, filters, baseCurrency, currencyOptions, viewingAllTenants, accountBudgets = {} }: Props) {
    const hasBudgets = Object.keys(accountBudgets ?? {}).length > 0;
    const confirm = useConfirm();
    const [search, setSearch] = useState(filters.search ?? '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Account | null>(null);
    const [serviceId, setServiceId] = useState<number | null>(null);
    const [collapsed, setCollapsed] = useState<Set<number>>(new Set());
    const [collapsedCodes, setCollapsedCodes] = useState<Set<string>>(new Set());
    const [expandedTenantsFor, setExpandedTenantsFor] = useState<Set<string>>(new Set());
    const [importOpen, setImportOpen] = useState(false);

    const forest = useMemo(() => (viewingAllTenants ? [] : buildForest(accounts as Account[])), [accounts, viewingAllTenants]);
    const mergedForest = useMemo(
        () => (viewingAllTenants ? buildMergedForest(accounts as MergedAccount[]) : []),
        [accounts, viewingAllTenants],
    );

    function toggleCode(code: string) {
        setCollapsedCodes((prev) => {
            const next = new Set(prev);
            if (next.has(code)) {
                next.delete(code);
            } else {
                next.add(code);
            }
            return next;
        });
    }

    function toggleTenantsFor(code: string) {
        setExpandedTenantsFor((prev) => {
            const next = new Set(prev);
            if (next.has(code)) {
                next.delete(code);
            } else {
                next.add(code);
            }
            return next;
        });
    }

    function toggle(id: number) {
        setCollapsed((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    }

    const form = useForm({
        code: '',
        name: '',
        type: 'asset' as AccountType,
        currency: baseCurrency,
        parent_id: null as number | null,
        opening_balance: '' as number | string,
    });


    function handleCodeChange(value: string) {
        const inferredType = TYPE_BY_CODE_PREFIX[value.charAt(0)];

        form.setData((data) => ({
            ...data,
            code: value,
            type: inferredType ?? data.type,
            parent_id: inferredType && inferredType !== data.type ? null : data.parent_id,
        }));
    }

    function openCreate() {
        setEditing(null);
        setServiceId(null);
        form.reset();
        form.clearErrors();
        setModalOpen(true);
    }

    function editTenantAccount(node: MergedAccountNode, tenant: MergedAccountTenant) {
        openEdit({
            id: tenant.account_id,
            tenant_id: tenant.tenant_id,
            code: node.code,
            name: node.name,
            type: node.type,
            currency: node.currency,
            parent_id: tenant.parent_id,
        } as unknown as Account);
    }

    function openEdit(account: Account) {
        setEditing(account);
        form.setData({
            code: account.code,
            name: account.name,
            type: account.type,
            currency: account.currency,
            parent_id: account.parent_id,
            opening_balance: '',
        });
        form.clearErrors();
        setModalOpen(true);
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        form.transform((data) => (editing ? data : { ...data, for_tenant: serviceId }));

        if (editing) {
            form.put(route('accounts.update', editing.id), { onSuccess: () => setModalOpen(false) });
        } else {
            form.post(route('accounts.store'), {
                onSuccess: () => {
                    setModalOpen(false);
                    form.reset();
                },
            });
        }
    }

    async function destroy(account: Account) {
        if (
            await confirm(`Delete account ${account.code} — ${account.name}?`, {
                variant: 'danger',
                confirmLabel: 'Delete',
            })
        ) {
            router.delete(route('accounts.destroy', account.id));
        }
    }

    function runSearch(e: FormEvent) {
        e.preventDefault();
        router.get(route('accounts.index'), { ...filters, search }, { preserveState: true });
    }

    function runFilters(next: Partial<Props['filters']>) {
        router.get(route('accounts.index'), { ...filters, search, ...next }, { preserveState: true });
    }

    function clearFilters() {
        setSearch('');
        router.get(route('accounts.index'), {}, { preserveState: true });
    }

    function openImport() {
        setImportOpen(true);
    }

    return (
        <>
            <Head title="Chart of Accounts" />

            <PageHeader
                title="Chart of Accounts"
                subtitle={
                    viewingAllTenants
                        ? 'Every service’s chart of accounts, combined.'
                        : 'Every posting account in the ledger, grouped by type.'
                }
                action={
                    (
                        <div className="d-flex flex-wrap gap-2">
                            {!viewingAllTenants && <ExportButton href={route('accounts.export', filters)} />}
                            {!viewingAllTenants && (
                                <Button variant="outline" leadingIcon={<Upload size={16} />} onClick={openImport}>
                                Import from Excel
                            </Button>
                            )}
                            <Button leadingIcon={<Plus size={16} />} onClick={openCreate}>
                                New Account
                            </Button>
                        </div>
                    )
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <FilterPanel active={Boolean(filters.type || filters.is_active || filters.search)}>
                        <form onSubmit={runSearch} className="af-filter-bar">
                            <Input
                                placeholder="Search by code or name…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                style={{ maxWidth: '260px' }}
                            />
                            <Select
                                value={filters.type ?? ''}
                                onChange={(e) => runFilters({ type: e.target.value || undefined })}
                                style={{ maxWidth: '160px' }}
                            >
                                <option value="">All types</option>
                                {ACCOUNT_TYPES.map((t) => (
                                    <option key={t.value} value={t.value}>
                                        {t.label}
                                    </option>
                                ))}
                            </Select>
                            <Select
                                value={filters.is_active ?? ''}
                                onChange={(e) => runFilters({ is_active: e.target.value || undefined })}
                                style={{ maxWidth: '160px' }}
                            >
                                <option value="">All statuses</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </Select>
                            <Button type="submit" variant="outline">
                                Search
                            </Button>
                            {(filters.type || filters.is_active || filters.search) && (
                                <Button type="button" variant="ghost" onClick={clearFilters}>
                                    Clear
                                </Button>
                            )}
                        </form>
                    </FilterPanel>
                </div>

                {accounts.length === 0 ? (
                    <EmptyState
                        icon={<ListTree size={20} />}
                        title="No accounts yet"
                        description="Create your first account to start building the chart of accounts."
                        action={<Button onClick={openCreate}>New Account</Button>}
                    />
                ) : viewingAllTenants ? (
                    <Table>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Code</Table.HeadCell>
                            <Table.HeadCell>Name</Table.HeadCell>
                            <Table.HeadCell>Normal Balance</Table.HeadCell>
                            <Table.HeadCell>Currency</Table.HeadCell>
                            <Table.HeadCell>Services</Table.HeadCell>
                            <Table.HeadCell className="text-end">Balance</Table.HeadCell>
                            <Table.HeadCell>Status</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {ACCOUNT_TYPES.map(({ value, label }) => {
                                const roots = mergedForest.filter((node) => node.type === value);
                                if (roots.length === 0) return null;

                                return (
                                    <Fragment key={value}>
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="px-3 py-2"
                                                style={{ backgroundColor: 'var(--af-surface-alt, rgba(0,0,0,0.02))', borderBottom: '1px solid var(--af-border)' }}
                                            >
                                                <Badge variant={typeBadgeVariant(value)}>{label}</Badge>
                                            </td>
                                        </tr>
                                        {roots.map((node) => (
                                            <MergedAccountTreeRows
                                                key={node.code}
                                                node={node}
                                                depth={0}
                                                collapsedCodes={collapsedCodes}
                                                onToggle={toggleCode}
                                                expandedTenantsFor={expandedTenantsFor}
                                                onToggleTenants={toggleTenantsFor}
                                                onEditTenantAccount={editTenantAccount}
                                                baseCurrency={baseCurrency}
                                            />
                                        ))}
                                    </Fragment>
                                );
                            })}
                        </tbody>
                    </Table>
                ) : (
                    <Table>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Code</Table.HeadCell>
                            <Table.HeadCell>Name</Table.HeadCell>
                            <Table.HeadCell>Normal Balance</Table.HeadCell>
                            <Table.HeadCell>Currency</Table.HeadCell>
                            <Table.HeadCell className="text-end">Balance</Table.HeadCell>
                            {hasBudgets && <Table.HeadCell className="text-end">Budget used (year to date)</Table.HeadCell>}
                            <Table.HeadCell>Status</Table.HeadCell>
                            <Table.HeadCell className="text-end pe-3">Actions</Table.HeadCell>
                        </Table.Head>
                        <tbody>
                            {ACCOUNT_TYPES.map(({ value, label }) => {
                                const roots = forest.filter((node) => node.type === value);
                                if (roots.length === 0) return null;

                                return (
                                    <Fragment key={value}>
                                        <tr>
                                            <td
                                                colSpan={hasBudgets ? 8 : 7}
                                                className="px-3 py-2"
                                                style={{ backgroundColor: 'var(--af-surface-alt, rgba(0,0,0,0.02))', borderBottom: '1px solid var(--af-border)' }}
                                            >
                                                <Badge variant={typeBadgeVariant(value)}>{label}</Badge>
                                            </td>
                                        </tr>
                                        {roots.map((node) => (
                                            <AccountTreeRows
                                                key={node.id}
                                                node={node}
                                                depth={0}
                                                collapsed={collapsed}
                                                onToggle={toggle}
                                                onEdit={openEdit}
                                                onDelete={destroy}
                                                baseCurrency={baseCurrency}
                                                budgets={accountBudgets}
                                            />
                                        ))}
                                    </Fragment>
                                );
                            })}
                        </tbody>
                    </Table>
                )}
            </Card>

            <Modal
                open={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editing ? 'Edit Account' : 'New Account'}
                footer={
                    <>
                        <Button variant="outline" onClick={() => setModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={submit} loading={form.processing}>
                            {editing ? 'Save changes' : 'Create account'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="d-flex flex-column gap-3">
                    {viewingAllTenants && !editing && (
                        <ServiceField value={serviceId} onChange={setServiceId} error={(form.errors as Record<string, string>).for_tenant} />
                    )}
                    <Input
                        label="Code"
                        help="A short number that identifies the account in your chart, for example 1210. Each code must be unique."
                        value={form.data.code}
                        onChange={(e) => handleCodeChange(e.target.value)}
                        error={form.errors.code}
                        placeholder="e.g. 1000"
                    />
                    <Input
                        label="Name"
                        help="What the account is called in reports, for example Cash and Bank or Office Rent."
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        error={form.errors.name}
                        placeholder="e.g. Cash"
                    />
                    <Select
                        label="Type"
                        help="Asset (what you own), Liability (what you owe), Equity (owner funds), Revenue (income) or Expense (costs). It decides which report the account appears on."
                        value={form.data.type}
                        onChange={(e) => form.setData((data) => ({ ...data, type: e.target.value as AccountType, parent_id: null }))}
                        error={form.errors.type}
                    >
                        {ACCOUNT_TYPES.map((t) => (
                            <option key={t.value} value={t.value}>
                                {t.label}
                            </option>
                        ))}
                    </Select>
                    <Select
                        label="Currency"
                        help="The currency this account is held in. Leave it as your base currency unless the account really holds foreign money."
                        value={form.data.currency}
                        onChange={(e) => form.setData('currency', e.target.value)}
                        error={form.errors.currency}
                    >
                        {currencyOptions.map((currency) => (
                            <option key={currency.code} value={currency.code}>
                                {currency.code} — {currency.name}
                            </option>
                        ))}
                    </Select>
                    <div>
                        <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                            Parent account (optional) <InfoTip>Put this account under another one to group them, for example Petty Cash under Cash and Bank. Parent accounts only group; you post to the child accounts.</InfoTip>
                        </label>
                        <AccountPicker
                            tenantId={editing ? ((editing as { tenant_id?: number }).tenant_id ?? null) : serviceId}
                            value={form.data.parent_id}
                            onChange={(id) => form.setData('parent_id', id)}
                            error={form.errors.parent_id}
                            placeholder="No parent"
                            filterType={form.data.type}
                            includeParents
                        />
                    </div>
                    {!editing && (
                        <Input
                            label="Opening balance (optional)"
                            help="The amount already in this account when you start using the system. It is posted as an opening entry against the Opening Balance Equity account. Leave empty to start from zero."
                            type="number"
                            step="0.01"
                            value={form.data.opening_balance}
                            onChange={(e) => form.setData('opening_balance', e.target.value)}
                            error={form.errors.opening_balance}
                            placeholder="0.00"
                        />
                    )}
                </form>
            </Modal>

            <ImportModal
                open={importOpen}
                onClose={() => setImportOpen(false)}
                action={route('accounts.import')}
                templateHref={route('accounts.import-template')}
                requiredColumns={['code', 'name', 'type']}
                optionalColumns={['normal_balance', 'parent_code', 'is_active', 'opening_balance']}
                notes={[
                    'type is one of asset, liability, equity, revenue or expense.',
                    'One row per account, with a header row.',
                    "A parent account's code must already exist or appear earlier in the file.",
                ]}
            />
        </>
    );
}
