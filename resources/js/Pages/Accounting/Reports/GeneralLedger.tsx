import { Popover } from '@headlessui/react';
import { Head, router } from '@inertiajs/react';
import { BookOpen, ChevronDown, Columns3 } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AccountPicker } from '@/Components/finance/AccountPicker';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { ExportMenu } from '@/Components/ui/ExportMenu';
import { Input } from '@/Components/ui/Input';
import { Table } from '@/Components/ui/Table';
import type { Account, GeneralLedgerReport } from '@/types/finance';
import { formatDate } from '@/utils/finance';

interface Props {
    account: Account | null;
    ledger: GeneralLedgerReport | null;
    costCenterLabel: string | null;
    filters: {
        account_id: number | null;
        from: string | null;
        to: string | null;
        cost_center_id: number | string | null;
    };
}

const COLUMNS = [
    { key: 'date', label: 'Date' },
    { key: 'description', label: 'Description' },
    { key: 'reference', label: 'Reference' },
    { key: 'cost_center', label: 'Center' },
    { key: 'debit', label: 'Debit' },
    { key: 'credit', label: 'Credit' },
    { key: 'balance', label: 'Balance' },
] as const;

type ColumnKey = (typeof COLUMNS)[number]['key'];

export default function GeneralLedger({ account, ledger, costCenterLabel, filters }: Props) {
    const [accountId, setAccountId] = useState<number | null>(filters.account_id);
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [costCenterId, setCostCenterId] = useState<number | string | null>(filters.cost_center_id);

    const [visible, setVisible] = useState<ColumnKey[]>(COLUMNS.map((c) => c.key));
    const shown = (key: ColumnKey) => visible.includes(key);
    const shownColumns = COLUMNS.filter((c) => shown(c.key));

    function toggleColumn(key: ColumnKey) {
        setVisible((current) => (current.includes(key) ? current.filter((k) => k !== key) : [...current, key]));
    }

    function applyFilter(e: FormEvent) {
        e.preventDefault();
        router.get(
            route('reports.general-ledger'),
            { account_id: accountId, from, to, cost_center_id: costCenterId },
            { preserveState: true },
        );
    }

    return (
        <>
            <Head title="General Ledger" />

            <PageHeader
                title="General Ledger"
                subtitle="Running balance for a single account, in posting order."
                action={
                    account && (
                        <ExportMenu
                            options={[
                                {
                                    label: 'Export to Excel',
                                    href: route('reports.general-ledger.export', {
                                        ...filters,
                                        columns: shownColumns.map((c) => c.key).join(','),
                                    }),
                                },
                            ]}
                        />
                    )
                }
            />

            <Card>
                <form onSubmit={applyFilter} className="row g-3 align-items-end mb-4">
                    <div className="col-md-4">
                        <label
                            className="d-block mb-1"
                            style={{
                                fontSize: '13px',
                                color: 'var(--af-label)',
                            }}
                        >
                            Account
                        </label>
                        <AccountPicker value={accountId} onChange={setAccountId} />
                    </div>
                    <div className="col-md-2">
                        <Input type="date" label="From" value={from} onChange={(e) => setFrom(e.target.value)} />
                    </div>
                    <div className="col-md-2">
                        <Input type="date" label="To" value={to} onChange={(e) => setTo(e.target.value)} />
                    </div>
                    <div className="col-md-2">
                        <Button type="submit" variant="outline">
                            Apply
                        </Button>
                    </div>
                </form>

                {costCenterLabel && (
                    <div className="mb-4 d-flex align-items-center" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                        Filtered to cost center: <strong className="ms-1">{costCenterLabel}</strong>
                        <button
                            type="button"
                            className="btn btn-link p-0 ms-2"
                            style={{ fontSize: '13px' }}
                            onClick={() => {
                                setCostCenterId(null);
                                router.get(route('reports.general-ledger'), { account_id: accountId, from, to }, { preserveState: true });
                            }}
                        >
                            Clear
                        </button>
                    </div>
                )}

                {!account || !ledger ? (
                    <EmptyState
                        icon={<BookOpen size={20} />}
                        title="Choose an account"
                        description="Select an account above to see its ledger."
                    />
                ) : (
                    <>
                        <div className="mb-3 d-flex align-items-center justify-content-between gap-3">
                            <div
                                style={{
                                    fontSize: '13px',
                                    color: 'var(--af-label)',
                                }}
                            >
                                {account.code} · {account.name} — normal balance: {account.normal_balance}
                            </div>

                            <Popover className="position-relative">
                                <Popover.Button as={Button} type="button" variant="outline">
                                    <Columns3 size={16} className="me-2" />
                                    Columns
                                    <ChevronDown size={14} className="ms-2" />
                                </Popover.Button>
                                <Popover.Panel
                                    className="position-absolute mt-1 py-2 px-3"
                                    style={{
                                        zIndex: 20,
                                        minWidth: '180px',
                                        right: 0,
                                        backgroundColor: 'var(--af-surface)',
                                        border: '1px solid var(--af-border)',
                                        borderRadius: 'var(--af-radius-sm)',
                                        boxShadow: '0 10px 15px -3px rgb(15 23 42 / 0.1)',
                                    }}
                                >
                                    {COLUMNS.map((column) => (
                                        <label
                                            key={column.key}
                                            className="d-flex align-items-center gap-2 py-1"
                                            style={{ fontSize: '14px' }}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={shown(column.key)}
                                                disabled={shown(column.key) && visible.length === 1}
                                                onChange={() => toggleColumn(column.key)}
                                            />
                                            {column.label}
                                        </label>
                                    ))}
                                </Popover.Panel>
                            </Popover>
                        </div>

                        <Table>
                            <Table.Head>
                                {shownColumns.map((column, index) => (
                                    <Table.HeadCell
                                        key={column.key}
                                        className={[
                                            index === 0 ? 'ps-3' : '',
                                            ['debit', 'credit', 'balance'].includes(column.key) ? 'text-end' : '',
                                            index === shownColumns.length - 1 ? 'pe-3' : '',
                                        ].join(' ')}
                                    >
                                        {column.label}
                                    </Table.HeadCell>
                                ))}
                            </Table.Head>
                            <tbody>
                                {ledger.lines.length === 0 ? (
                                    <Table.Row>
                                        <Table.Cell
                                            colSpan={shownColumns.length}
                                            className="text-center py-4"
                                            style={{ color: 'var(--af-label)' }}
                                        >
                                            No posted activity in this range.
                                        </Table.Cell>
                                    </Table.Row>
                                ) : (
                                    ledger.lines.map((line, i) => (
                                        <Table.Row key={i}>
                                            {shown('date') && <Table.Cell className="ps-3">{formatDate(line.date)}</Table.Cell>}
                                            {shown('description') && <Table.Cell>{line.description}</Table.Cell>}
                                            {shown('reference') && <Table.Cell>{line.reference ?? '—'}</Table.Cell>}
                                            {shown('cost_center') && <Table.Cell>{line.cost_center ?? '—'}</Table.Cell>}
                                            {shown('debit') && (
                                                <Table.Cell className="text-end">
                                                    {line.debit > 0 && <MoneyDisplay amount={line.debit} />}
                                                </Table.Cell>
                                            )}
                                            {shown('credit') && (
                                                <Table.Cell className="text-end">
                                                    {line.credit > 0 && <MoneyDisplay amount={line.credit} />}
                                                </Table.Cell>
                                            )}
                                            {shown('balance') && (
                                                <Table.Cell className="text-end pe-3">
                                                    <MoneyDisplay amount={line.balance} />
                                                </Table.Cell>
                                            )}
                                        </Table.Row>
                                    ))
                                )}
                                <Table.Row style={{ fontWeight: 600 }}>
                                    {shown('balance') && shownColumns.length > 1 ? (
                                        <>
                                            <Table.Cell className="ps-3" colSpan={shownColumns.length - 1}>
                                                Ending balance
                                            </Table.Cell>
                                            <Table.Cell className="text-end pe-3">
                                                <MoneyDisplay amount={ledger.ending_balance} />
                                            </Table.Cell>
                                        </>
                                    ) : (
                                        <Table.Cell className="ps-3 pe-3" colSpan={shownColumns.length}>
                                            Ending balance: <MoneyDisplay amount={ledger.ending_balance} />
                                        </Table.Cell>
                                    )}
                                </Table.Row>
                            </tbody>
                        </Table>
                    </>
                )}
            </Card>
        </>
    );
}
