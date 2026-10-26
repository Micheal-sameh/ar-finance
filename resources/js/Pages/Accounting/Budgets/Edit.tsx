import { Head, Link, router, usePage } from '@inertiajs/react';
import { Copy, History, Save, Trash2, Upload } from 'lucide-react';
import { ClipboardEvent, useEffect, useMemo, useState } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { useConfirm } from '@/Components/ui/ConfirmProvider';
import { ExportButton } from '@/Components/ui/ExportButton';
import { ImportModal } from '@/Components/ui/ImportModal';
import { InfoTip } from '@/Components/ui/InfoTip';
import { useAccounts } from '@/hooks/useAccounts';
import type { AccountOption, AccountType, BudgetEditorData, BudgetEditorLine, CostCenterOption } from '@/types/finance';

interface Props {
    budget: BudgetEditorData;
    costCenters: CostCenterOption[];
    /** Lines just read from an uploaded spreadsheet, waiting for review. */
    imported: BudgetEditorLine[] | null;
}

interface Row {
    uid: number;
    account_id: number;
    cost_center_id: number | '';
    months: string[];
}

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const STICKY: React.CSSProperties = { position: 'sticky', left: 0, backgroundColor: 'var(--af-surface)', zIndex: 1 };

let nextUid = 1;

function amountToText(amount: number | undefined): string {
    return amount ? String(Math.round(amount * 100) / 100) : '';
}

function lineToRow(line: BudgetEditorLine): Row {
    return {
        uid: nextUid++,
        account_id: line.account_id,
        cost_center_id: line.cost_center_id ?? '',
        months: MONTHS.map((_, index) => amountToText(line.months[index + 1])),
    };
}

function monthValues(row: Row): number[] {
    return row.months.map((text) => {
        const value = parseFloat(text);

        return Number.isFinite(value) ? value : 0;
    });
}

function rowTotal(row: Row): number {
    return monthValues(row).reduce((sum, value) => sum + value, 0);
}

function snapshot(rows: Row[]): string {
    return JSON.stringify(rows.map((row) => [row.account_id, row.cost_center_id, row.months]));
}

/** Splits a yearly amount over 12 months in cents, putting any leftover cent in the last month. */
function spreadEvenly(total: number): string[] {
    const cents = Math.round(total * 100);
    const base = Math.floor(cents / 12);
    const remainder = cents - base * 12;

    return MONTHS.map((_, index) => amountToText((index === 11 ? base + remainder : base) / 100));
}

export default function BudgetsEdit({ budget, costCenters, imported }: Props) {
    const confirm = useConfirm();
    const { auth, errors } = usePage<{ auth: { permissions: string[] }; errors: Record<string, string> }>().props;
    const canManage = auth.permissions.includes('accounts.manage');
    const { accounts, loading } = useAccounts();
    const accountById = useMemo(() => new Map<number, AccountOption>(accounts.map((account) => [account.id, account])), [accounts]);

    const [rows, setRows] = useState<Row[]>(() => budget.lines.map(lineToRow));
    const [saved, setSaved] = useState(() => snapshot(rows));
    const [processing, setProcessing] = useState(false);
    const [growth, setGrowth] = useState('0');
    const [pickerKey, setPickerKey] = useState(0);
    const [importOpen, setImportOpen] = useState(false);

    // An uploaded spreadsheet fills the page for review; nothing is saved until Save budget.
    useEffect(() => {
        if (imported) {
            setRows(imported.map(lineToRow));
        }
    }, [imported]);

    const dirty = snapshot(rows) !== saved;
    const hasPreviousBudget = budget.previous_budget.length > 0;
    const hasPreviousActuals = Object.keys(budget.previous_actuals).length > 0;
    const errorMessages = Array.from(new Set(Object.values(errors)));

    function typeOf(row: Row): AccountType | undefined {
        return accountById.get(row.account_id)?.type;
    }

    function updateRow(uid: number, change: Partial<Row>) {
        setRows((current) => current.map((row) => (row.uid === uid ? { ...row, ...change } : row)));
    }

    function setMonth(uid: number, index: number, text: string) {
        setRows((current) => current.map((row) => (row.uid === uid ? { ...row, months: row.months.map((value, i) => (i === index ? text : value)) } : row)));
    }

    function pasteIntoRow(event: ClipboardEvent<HTMLInputElement>, uid: number, startIndex: number) {
        const values = event.clipboardData
            .getData('text')
            .split(/[\t,\n\r ]+/)
            .map((part) => part.replace(/[^0-9.\-]/g, ''))
            .filter((part) => part !== '');

        if (values.length < 2) {
            return;
        }

        event.preventDefault();
        setRows((current) =>
            current.map((row) =>
                row.uid === uid ? { ...row, months: row.months.map((value, i) => (i >= startIndex && i - startIndex < values.length ? amountToText(parseFloat(values[i - startIndex])) : value)) } : row,
            ),
        );
    }

    function addAccount(accountId: number | null) {
        if (accountId === null) {
            return;
        }

        setRows((current) => [...current, { uid: nextUid++, account_id: accountId, cost_center_id: '', months: MONTHS.map(() => '') }]);
        setPickerKey((key) => key + 1);
    }

    function sameEveryMonth(row: Row) {
        const first = row.months.find((text) => text !== '') ?? '';

        updateRow(row.uid, { months: MONTHS.map(() => first) });
    }

    async function replaceRows(next: Row[], message: string) {
        if (rows.some((row) => rowTotal(row) !== 0) && !(await confirm(message, { variant: 'primary', confirmLabel: 'Replace amounts' }))) {
            return;
        }

        setRows(next);
    }

    function copyPreviousBudget() {
        replaceRows(budget.previous_budget.map(lineToRow), `This replaces what you have entered with the ${budget.year - 1} budget.`);
    }

    function usePreviousActuals() {
        const factor = 1 + (parseFloat(growth) || 0) / 100;
        const next: Row[] = Object.entries(budget.previous_actuals)
            .map(([accountId, months]) => ({
                uid: nextUid++,
                account_id: Number(accountId),
                cost_center_id: '' as const,
                months: MONTHS.map((_, index) => amountToText(Math.max(0, (months[index + 1] ?? 0) * factor))),
            }))
            .filter((row) => rowTotal(row) > 0);

        replaceRows(next, `This replaces what you have entered with ${budget.year - 1}'s actual figures.`);
    }

    function save() {
        router.put(
            route('budgets.update', budget.year),
            {
                lines: rows.map((row) => ({
                    account_id: row.account_id,
                    cost_center_id: row.cost_center_id === '' ? null : row.cost_center_id,
                    months: Object.fromEntries(monthValues(row).map((value, index) => [index + 1, value])),
                })),
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onSuccess: () => setSaved(snapshot(rows)),
                onFinish: () => setProcessing(false),
            },
        );
    }

    function section(kind: 'revenue' | 'expense') {
        const sectionRows = rows
            .filter((row) => typeOf(row) === kind)
            .sort((a, b) => (accountById.get(a.account_id)?.code ?? '').localeCompare(accountById.get(b.account_id)?.code ?? ''));
        const monthTotals = MONTHS.map((_, index) => sectionRows.reduce((sum, row) => sum + monthValues(row)[index], 0));

        return { sectionRows, monthTotals, total: monthTotals.reduce((sum, value) => sum + value, 0) };
    }

    const revenue = section('revenue');
    const expenses = section('expense');
    const netByMonth = MONTHS.map((_, index) => revenue.monthTotals[index] - expenses.monthTotals[index]);

    function renderSection(kind: 'revenue' | 'expense', title: string, data: ReturnType<typeof section>, hint: string) {
        return (
            <>
                <tr>
                    <td colSpan={costCenters.length > 0 ? 16 : 15} style={{ ...STICKY, padding: '14px 0 6px', fontWeight: 700, color: 'var(--af-navy)' }}>
                        {title} <span style={{ color: 'var(--af-label)', fontWeight: 400, fontSize: '12px' }}>— {hint}</span>
                    </td>
                </tr>
                {data.sectionRows.map((row) => {
                    const account = accountById.get(row.account_id);

                    return (
                        <tr key={row.uid} style={{ borderTop: '1px solid var(--af-border)' }}>
                            <td style={{ ...STICKY, padding: '6px 10px 6px 0', minWidth: '200px', maxWidth: '240px' }}>
                                <div style={{ fontWeight: 500, fontSize: '13px' }}>{account?.name}</div>
                                <div style={{ fontSize: '11px', color: 'var(--af-label)' }}>{account?.code}</div>
                            </td>
                            {costCenters.length > 0 && (
                                <td style={{ padding: '4px' }}>
                                    <select
                                        className="form-select form-select-sm"
                                        aria-label={`Cost center for ${account?.name}`}
                                        style={{ minWidth: '130px', fontSize: '12px' }}
                                        disabled={!canManage}
                                        value={row.cost_center_id}
                                        onChange={(e) => updateRow(row.uid, { cost_center_id: e.target.value ? Number(e.target.value) : '' })}
                                    >
                                        <option value="">Whole service</option>
                                        {costCenters.map((center) => (
                                            <option key={center.id} value={center.id}>
                                                {center.name}
                                            </option>
                                        ))}
                                    </select>
                                </td>
                            )}
                            {row.months.map((text, index) => (
                                <td key={index} style={{ padding: '4px 2px' }}>
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        inputMode="decimal"
                                        className="form-control form-control-sm text-end"
                                        aria-label={`${account?.name} ${MONTHS[index]}`}
                                        style={{ width: '88px', fontSize: '13px', fontVariantNumeric: 'tabular-nums' }}
                                        disabled={!canManage}
                                        value={text}
                                        onChange={(e) => setMonth(row.uid, index, e.target.value)}
                                        onPaste={(e) => pasteIntoRow(e, row.uid, index)}
                                    />
                                </td>
                            ))}
                            <td style={{ padding: '4px 2px' }}>
                                <input
                                    key={rowTotal(row)}
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    inputMode="decimal"
                                    className="form-control form-control-sm text-end fw-semibold"
                                    aria-label={`${account?.name} yearly total`}
                                    title="Type a yearly amount and press Enter — it is spread evenly over the 12 months."
                                    style={{ width: '104px', fontSize: '13px', fontVariantNumeric: 'tabular-nums', backgroundColor: '#F6F8FB' }}
                                    disabled={!canManage}
                                    defaultValue={rowTotal(row) ? Math.round(rowTotal(row) * 100) / 100 : ''}
                                    onBlur={(e) => {
                                        const value = parseFloat(e.target.value);

                                        if (Number.isFinite(value) && Math.round(value * 100) !== Math.round(rowTotal(row) * 100)) {
                                            updateRow(row.uid, { months: spreadEvenly(value) });
                                        }
                                    }}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                            e.currentTarget.blur();
                                        }
                                    }}
                                />
                            </td>
                            {canManage && (
                                <td style={{ padding: '4px 0 4px 4px', whiteSpace: 'nowrap' }}>
                                    <Button size="sm" variant="ghost" type="button" title="Use the first amount for every month" aria-label="Same amount every month" onClick={() => sameEveryMonth(row)}>
                                        <Copy size={14} />
                                    </Button>
                                    <Button size="sm" variant="ghost" type="button" title="Remove this line" aria-label={`Remove ${account?.name}`} onClick={() => setRows((current) => current.filter((r) => r.uid !== row.uid))}>
                                        <Trash2 size={14} />
                                    </Button>
                                </td>
                            )}
                        </tr>
                    );
                })}
                {data.sectionRows.length === 0 && (
                    <tr>
                        <td colSpan={costCenters.length > 0 ? 16 : 15} style={{ ...STICKY, padding: '6px 0 10px', color: 'var(--af-label)', fontSize: '13px' }}>
                            {kind === 'revenue' ? 'No revenue accounts budgeted yet.' : 'No expense accounts budgeted yet.'}
                        </td>
                    </tr>
                )}
                {canManage && (
                    <tr>
                        <td colSpan={costCenters.length > 0 ? 16 : 15} style={{ ...STICKY, padding: '6px 0 10px' }}>
                            <div style={{ maxWidth: '320px' }}>
                                <AccountPicker
                                    key={`${kind}-${pickerKey}`}
                                    value={null}
                                    onChange={addAccount}
                                    filterType={kind}
                                    floating
                                    placeholder={kind === 'revenue' ? '+ Add a revenue account…' : '+ Add an expense account…'}
                                />
                            </div>
                        </td>
                    </tr>
                )}
                <tr style={{ borderTop: '2px solid var(--af-navy)', fontWeight: 700 }}>
                    <td style={{ ...STICKY, padding: '8px 10px 8px 0' }}>Total {title.toLowerCase()}</td>
                    {costCenters.length > 0 && <td />}
                    {data.monthTotals.map((value, index) => (
                        <td key={index} className="text-end" style={{ padding: '8px 10px', fontSize: '13px', fontVariantNumeric: 'tabular-nums' }}>
                            <MoneyDisplay amount={value} currency="" />
                        </td>
                    ))}
                    <td className="text-end" style={{ padding: '8px 10px', fontSize: '13px' }}>
                        <MoneyDisplay amount={data.total} currency="" />
                    </td>
                    {canManage && <td />}
                </tr>
            </>
        );
    }

    return (
        <>
            <Head title={`${budget.year} Budget`} />

            <PageHeader
                title={`${budget.year} budget`}
                subtitle="A planned amount for each month. Type each month, paste a row from a spreadsheet, or type a yearly total to spread it evenly."
                action={
                    <div className="d-flex align-items-center gap-2">
                        {dirty && <span style={{ fontSize: '13px', color: 'var(--af-warning)' }}>Unsaved changes</span>}
                        {canManage && (
                            <Button variant="outline" leadingIcon={<Upload size={16} />} onClick={() => setImportOpen(true)}>
                                Import
                            </Button>
                        )}
                        {budget.exists && <ExportButton href={route('budgets.export', budget.year)} />}
                        <Link href={route('reports.budget-vs-actual', { from: `${budget.year}-01-01`, to: `${budget.year}-12-31` })}>
                            <Button variant="outline">Compare with actual</Button>
                        </Link>
                        {canManage && (
                            <Button leadingIcon={<Save size={16} />} onClick={save} loading={processing} disabled={!dirty}>
                                Save budget
                            </Button>
                        )}
                    </div>
                }
            />

            {errorMessages.length > 0 && (
                <div className="mb-3 p-3" style={{ border: '1px solid var(--af-danger)', borderRadius: 'var(--af-radius-sm)', color: 'var(--af-danger)', fontSize: '13px' }}>
                    {errorMessages.slice(0, 4).map((message) => (
                        <div key={message}>{message}</div>
                    ))}
                </div>
            )}

            <Card>
                {canManage && (
                    <div className="d-flex flex-wrap align-items-end gap-3 mb-4 pb-3" style={{ borderBottom: '1px solid var(--af-border)' }}>
                        <div style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                            <div style={{ fontWeight: 600, color: 'var(--af-navy)', marginBottom: '4px' }}>
                                Start from last year <InfoTip>Pre-fill this budget instead of typing every month. You can still change any amount afterwards.</InfoTip>
                            </div>
                            Save time by using what you planned or spent in {budget.year - 1}.
                        </div>
                        <Button type="button" variant="outline" size="sm" leadingIcon={<History size={14} />} disabled={!hasPreviousBudget} onClick={copyPreviousBudget}>
                            Copy {budget.year - 1} budget
                        </Button>
                        <div className="d-flex align-items-end gap-2">
                            <div>
                                <label htmlFor="growth" style={{ fontSize: '12px', color: 'var(--af-label)' }}>
                                    Add / subtract %
                                </label>
                                <input
                                    id="growth"
                                    type="number"
                                    step="1"
                                    className="form-control form-control-sm"
                                    style={{ width: '90px' }}
                                    value={growth}
                                    onChange={(e) => setGrowth(e.target.value)}
                                />
                            </div>
                            <Button type="button" variant="outline" size="sm" disabled={!hasPreviousActuals} onClick={usePreviousActuals}>
                                Use {budget.year - 1} actuals
                            </Button>
                        </div>
                    </div>
                )}

                {loading ? (
                    <div style={{ color: 'var(--af-label)', padding: '24px 0' }}>Loading accounts…</div>
                ) : (
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ borderCollapse: 'separate', borderSpacing: 0, fontSize: '14px' }}>
                            <thead>
                                <tr style={{ color: 'var(--af-label)', fontSize: '12px', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                                    <th style={{ ...STICKY, padding: '6px 10px 6px 0', textAlign: 'left' }}>Account</th>
                                    {costCenters.length > 0 && <th style={{ padding: '6px 4px', textAlign: 'left' }}>Applies to</th>}
                                    {MONTHS.map((month) => (
                                        <th key={month} className="text-end" style={{ padding: '6px 10px' }}>
                                            {month}
                                        </th>
                                    ))}
                                    <th className="text-end" style={{ padding: '6px 10px' }}>
                                        Year
                                    </th>
                                    {canManage && <th />}
                                </tr>
                            </thead>
                            <tbody>
                                {renderSection('revenue', 'Revenue', revenue, 'money you plan to earn')}
                                {renderSection('expense', 'Expenses', expenses, 'money you plan to spend')}
                                <tr style={{ borderTop: '2px solid var(--af-navy)', fontWeight: 700, fontSize: '15px' }}>
                                    <td style={{ ...STICKY, padding: '12px 10px 12px 0' }}>Planned profit</td>
                                    {costCenters.length > 0 && <td />}
                                    {netByMonth.map((value, index) => (
                                        <td key={index} className="text-end" style={{ padding: '12px 10px', fontSize: '13px', color: value < 0 ? 'var(--af-danger)' : 'var(--af-success)', fontVariantNumeric: 'tabular-nums' }}>
                                            <MoneyDisplay amount={value} currency="" />
                                        </td>
                                    ))}
                                    <td className="text-end" style={{ padding: '12px 10px', color: revenue.total - expenses.total < 0 ? 'var(--af-danger)' : 'var(--af-success)' }}>
                                        <MoneyDisplay amount={revenue.total - expenses.total} currency="" />
                                    </td>
                                    {canManage && <td />}
                                </tr>
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            <ImportModal
                open={importOpen}
                onClose={() => setImportOpen(false)}
                action={route('budgets.import', budget.year)}
                templateHref={route('budgets.import-template')}
                title={`Import ${budget.year} budget from Excel`}
                requiredColumns={['Code']}
                optionalColumns={['Account', 'Applies to', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']}
                notes={[
                    'The template lists every revenue and expense account — just fill in the months.',
                    'Code decides the account. Applies to is a cost center name; leave it empty for the whole service.',
                    'The amounts replace what is on this page so you can review them. Nothing is saved until you click Save budget.',
                ]}
            />
        </>
    );
}
