import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { MoneyDisplay } from '@/Components/finance/MoneyDisplay';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Input } from '@/Components/ui/Input';

interface VatReturnReport {
    from: string;
    to: string;
    sales_subtotal: number;
    output_vat: number;
    purchases_subtotal: number;
    input_vat: number;
    net_vat_payable: number;
}

interface Props {
    report: VatReturnReport;
    filters: { from: string; to: string };
}

function Row({ label, amount, bold = false }: { label: string; amount: number; bold?: boolean }) {
    return (
        <div className="d-flex justify-content-between" style={{ fontSize: '14px', padding: '6px 0', fontWeight: bold ? 600 : 400 }}>
            <span>{label}</span>
            <MoneyDisplay amount={amount} />
        </div>
    );
}

export default function VatReturn({ report, filters }: Props) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    function applyFilter(e: FormEvent) {
        e.preventDefault();
        router.get(route('reports.vat-return'), { from, to }, { preserveState: true });
    }

    return (
        <>
            <Head title="VAT Return" />

            <PageHeader
                title="VAT Return"
                subtitle="Output VAT (sales) less input VAT (purchases) for the period — derived from sent invoices and approved bills, matching what actually posted to the ledger."
            />

            <Card>
                <form onSubmit={applyFilter} className="d-flex align-items-end gap-2 mb-4">
                    <div style={{ maxWidth: '180px' }}>
                        <Input type="date" label="From" value={from} onChange={(e) => setFrom(e.target.value)} />
                    </div>
                    <div style={{ maxWidth: '180px' }}>
                        <Input type="date" label="To" value={to} onChange={(e) => setTo(e.target.value)} />
                    </div>
                    <Button type="submit" variant="outline">
                        Apply
                    </Button>
                </form>

                <div style={{ fontSize: '13px', fontWeight: 600, color: 'var(--af-navy)', marginBottom: '8px' }}>
                    Sales (Output VAT)
                </div>
                <Row label="Sales subtotal" amount={report.sales_subtotal} />
                <Row label="Output VAT collected" amount={report.output_vat} bold />

                <div style={{ fontSize: '13px', fontWeight: 600, color: 'var(--af-navy)', margin: '20px 0 8px' }}>
                    Purchases (Input VAT)
                </div>
                <Row label="Purchases subtotal" amount={report.purchases_subtotal} />
                <Row label="Input VAT paid" amount={report.input_vat} bold />

                <div
                    className="d-flex align-items-center justify-content-between mt-4 pt-3"
                    style={{ borderTop: '2px solid var(--af-navy)', fontSize: '16px', fontWeight: 700 }}
                >
                    <span>Net VAT Payable</span>
                    <span style={{ color: report.net_vat_payable >= 0 ? 'var(--af-text)' : 'var(--af-success)' }}>
                        <MoneyDisplay amount={report.net_vat_payable} />
                    </span>
                </div>
                {report.net_vat_payable < 0 && (
                    <div className="mt-2" style={{ fontSize: '12px', color: 'var(--af-label)' }}>
                        Negative means input VAT exceeded output VAT — a refund/credit position.
                    </div>
                )}
            </Card>
        </>
    );
}
