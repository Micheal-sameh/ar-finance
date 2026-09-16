import { Head, router, useForm } from '@inertiajs/react';
import { ChevronDown, ChevronRight, ListTree, Pencil, Plus, Trash2 } from 'lucide-react';
import { Fragment, FormEvent, useMemo, useState } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { useConfirm } from '@/Components/ui/ConfirmProvider';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Input } from '@/Components/ui/Input';
import { Modal } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { Table } from '@/Components/ui/Table';
import { AppLayout } from '@/Layouts/AppLayout';
import type { Account, AccountType } from '@/types/finance';

interface Props {
    accounts: Account[];
    filters: { type?: string; is_active?: string; search?: string };
    baseCurrency: string;
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
}

function AccountTreeRows({ node, depth, collapsed, onToggle, onEdit, onDelete, baseCurrency }: AccountTreeRowsProps) {
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
                <Table.Cell className="text-end">
                    <MoneyDisplay
                        amount={hasChildren && !isExpanded ? subtreeBalance(node) : node.balance ?? 0}
                        currency={baseCurrency}
                    />
                </Table.Cell>
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

export default function AccountsIndex({ accounts, filters, baseCurrency }: Props) {
    const confirm = useConfirm();
    const [search, setSearch] = useState(filters.search ?? '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Account | null>(null);
    const [collapsed, setCollapsed] = useState<Set<number>>(new Set());

    const forest = useMemo(() => buildForest(accounts), [accounts]);

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
        form.reset();
        form.clearErrors();
        setModalOpen(true);
    }

    function openEdit(account: Account) {
        setEditing(account);
        form.setData({
            code: account.code,
            name: account.name,
            type: account.type,
            parent_id: account.parent_id,
            opening_balance: '',
        });
        form.clearErrors();
        setModalOpen(true);
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        if (editing) {
            form.put(route('accounts.update', editing.id), { onSuccess: () => setModalOpen(false) });
        } else {
            form.post(route('accounts.store'), { onSuccess: () => setModalOpen(false) });
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

    return (
        <AppLayout>
            <Head title="Chart of Accounts" />

            <PageHeader
                title="Chart of Accounts"
                subtitle="Every posting account in the ledger, grouped by type."
                action={
                    <Button leadingIcon={<Plus size={16} />} onClick={openCreate}>
                        New Account
                    </Button>
                }
            />

            <Card padded={false}>
                <div className="p-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                    <form onSubmit={runSearch} className="d-flex gap-2" style={{ maxWidth: '320px' }}>
                        <Input
                            placeholder="Search by code or name…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                    </form>
                </div>

                {accounts.length === 0 ? (
                    <EmptyState
                        icon={<ListTree size={20} />}
                        title="No accounts yet"
                        description="Create your first account to start building the chart of accounts."
                        action={<Button onClick={openCreate}>New Account</Button>}
                    />
                ) : (
                    <Table>
                        <Table.Head>
                            <Table.HeadCell className="ps-3">Code</Table.HeadCell>
                            <Table.HeadCell>Name</Table.HeadCell>
                            <Table.HeadCell>Normal Balance</Table.HeadCell>
                            <Table.HeadCell className="text-end">Balance</Table.HeadCell>
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
                                                colSpan={6}
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
                    <Input
                        label="Code"
                        value={form.data.code}
                        onChange={(e) => handleCodeChange(e.target.value)}
                        error={form.errors.code}
                        placeholder="e.g. 1000"
                    />
                    <Input
                        label="Name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        error={form.errors.name}
                        placeholder="e.g. Cash"
                    />
                    <Select
                        label="Type"
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
                    <div>
                        <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                            Parent account (optional)
                        </label>
                        <AccountPicker
                            value={form.data.parent_id}
                            onChange={(id) => form.setData('parent_id', id)}
                            error={form.errors.parent_id}
                            placeholder="No parent"
                            filterType={form.data.type}
                        />
                    </div>
                    {!editing && (
                        <Input
                            label="Opening balance (optional)"
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
        </AppLayout>
    );
}
