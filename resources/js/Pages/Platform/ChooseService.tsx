import { Head, Link } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';

interface Props {
    tenants: { id: number; name: string }[];
    continueUrl: string;
}

/**
 * Shown to a Platform Admin opening a "create" page with no service in
 * play. Picking one re-opens the same page with `for_tenant`, so the form
 * is built from — and saves into — that service without switching into it.
 */
export default function ChooseService({ tenants, continueUrl }: Props) {
    return (
        <>
            <Head title="Choose a service" />

            <PageHeader title="Choose a service" subtitle="Which service is this for?" />

            <Card>
                {tenants.length === 0 ? (
                    <EmptyState title="No active services" description="Create or activate a service first." />
                ) : (
                    <div className="d-flex flex-column gap-2">
                        {tenants.map((tenant) => (
                            <Link
                                key={tenant.id}
                                href={`${continueUrl}?for_tenant=${tenant.id}`}
                                className="d-flex align-items-center gap-2 p-3"
                                style={{ border: '1px solid var(--af-border)', borderRadius: 'var(--af-radius-sm)', color: 'var(--af-text)' }}
                            >
                                <Building2 size={16} />
                                {tenant.name}
                            </Link>
                        ))}
                    </div>
                )}
            </Card>
        </>
    );
}
