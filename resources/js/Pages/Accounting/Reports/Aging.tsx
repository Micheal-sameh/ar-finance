import { Head, router } from '@inertiajs/react';
import { Columns2, FileText, Truck } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AgingRow, AgingTable, AgingTotals } from '@/Components/finance/AgingTable';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Input } from '@/Components/ui/Input';
import { AppLayout } from '@/Layouts/AppLayout';

interface AgingReport {
    as_of: string;
    base_currency: string;
    rows: AgingRow[];
    totals: AgingTotals;
}

interface Props {
    arReport: AgingReport;
    apReport: AgingReport;
    filters: { as_of: string };
}

type ViewMode = 'split' | 'ar' | 'ap';

const VIEW_OPTIONS: { mode: ViewMode; label: string; icon: typeof Columns2 }[] = [
    { mode: 'ar', label: 'AR only', icon: FileText },
    { mode: 'split', label: 'Split view', icon: Columns2 },
    { mode: 'ap', label: 'AP only', icon: Truck },
];

function AgingSection({
    title,
    report,
    nameHeader,
    emptyDescription,
}: {
    title: string;
    report: AgingReport;
    nameHeader: string;
    emptyDescription: string;
}) {
    return (
        <Card>
            <div className="mb-3" style={{ fontSize: '15px', fontWeight: 700, color: 'var(--af-navy)' }}>
                {title}
            </div>
            {report.rows.length === 0 ? (
                <EmptyState title="Nothing outstanding" description={emptyDescription} />
            ) : (
                <AgingTable nameHeader={nameHeader} rows={report.rows} totals={report.totals} currency={report.base_currency} />
            )}
        </Card>
    );
}

export default function Aging({ arReport, apReport, filters }: Props) {
    const [asOf, setAsOf] = useState(filters.as_of);
    const [view, setView] = useState<ViewMode>('split');

    function applyFilter(e: FormEvent) {
        e.preventDefault();
        router.get(route('reports.aging'), { as_of: asOf }, { preserveState: true });
    }

    return (
        <AppLayout>
            <Head title="AR/AP Aging" />

            <PageHeader
                title="AR/AP Aging"
                subtitle="How much each client owes you, and how much you owe each vendor, by how overdue it is."
            />

            <div className="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-3">
                <form onSubmit={applyFilter} className="d-flex align-items-end gap-2">
                    <div style={{ maxWidth: '200px' }}>
                        <Input type="date" label="As of" value={asOf} onChange={(e) => setAsOf(e.target.value)} />
                    </div>
                    <Button type="submit" variant="outline">
                        Apply
                    </Button>
                </form>

                <div className="d-flex gap-1 p-1" style={{ backgroundColor: 'var(--af-bg)', borderRadius: 'var(--af-radius-sm)' }}>
                    {VIEW_OPTIONS.map(({ mode, label, icon: Icon }) => (
                        <Button
                            key={mode}
                            type="button"
                            size="sm"
                            variant={view === mode ? 'primary' : 'ghost'}
                            onClick={() => setView(mode)}
                        >
                            <Icon size={14} className="me-1" />
                            {label}
                        </Button>
                    ))}
                </div>
            </div>

            {view === 'split' ? (
                <div className="row g-3">
                    <div className="col-lg-6">
                        <AgingSection
                            title="AR Aging (clients)"
                            report={arReport}
                            nameHeader="Client"
                            emptyDescription="No sent invoices are currently awaiting payment."
                        />
                    </div>
                    <div className="col-lg-6">
                        <AgingSection
                            title="AP Aging (vendors)"
                            report={apReport}
                            nameHeader="Vendor"
                            emptyDescription="No approved bills are currently awaiting payment."
                        />
                    </div>
                </div>
            ) : view === 'ar' ? (
                <AgingSection
                    title="AR Aging (clients)"
                    report={arReport}
                    nameHeader="Client"
                    emptyDescription="No sent invoices are currently awaiting payment."
                />
            ) : (
                <AgingSection
                    title="AP Aging (vendors)"
                    report={apReport}
                    nameHeader="Vendor"
                    emptyDescription="No approved bills are currently awaiting payment."
                />
            )}
        </AppLayout>
    );
}
