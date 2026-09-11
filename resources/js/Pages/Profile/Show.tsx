import { Head } from '@inertiajs/react';
import { PageHeader } from '@/Components/layout/PageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Card } from '@/Components/ui/Card';
import { AppLayout } from '@/Layouts/AppLayout';

interface Props {
    user: {
        name: string;
        email: string;
        tenant_name: string | null;
        membership_code: string | null;
        roles: string[];
    };
}

function Field({ label, value }: { label: string; value: string }) {
    return (
        <div className="mb-3">
            <div style={{ fontSize: '12px', color: 'var(--af-label)', marginBottom: '2px' }}>{label}</div>
            <div style={{ fontSize: '14px', color: 'var(--af-text)' }}>{value}</div>
        </div>
    );
}

export default function ProfileShow({ user }: Props) {
    return (
        <AppLayout>
            <Head title="Profile" />

            <PageHeader title="Profile" subtitle="Your account details" />

            <div className="row">
                <div className="col-md-6">
                    <Card>
                        <Field label="Name" value={user.name} />
                        <Field label="Email" value={user.email} />
                        {user.tenant_name && <Field label="Organization" value={user.tenant_name} />}
                        {user.membership_code && <Field label="Membership Code" value={user.membership_code} />}
                        <div>
                            <div style={{ fontSize: '12px', color: 'var(--af-label)', marginBottom: '6px' }}>Roles</div>
                            <div className="d-flex gap-2 flex-wrap">
                                {user.roles.length > 0 ? (
                                    user.roles.map((role) => (
                                        <Badge key={role} variant="info">
                                            {role}
                                        </Badge>
                                    ))
                                ) : (
                                    <span style={{ fontSize: '13px', color: 'var(--af-label)' }}>No roles assigned</span>
                                )}
                            </div>
                        </div>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
