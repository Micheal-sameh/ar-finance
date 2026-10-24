import { Link } from '@inertiajs/react';
import { AuthShell } from '@/Components/auth/AuthShell';

interface Props {
    codes: string[];
}

export default function RecoveryCodes({ codes }: Props) {
    return (
        <AuthShell
            title="Recovery codes"
            heading="Save your recovery codes"
            description="If you lose access to your authenticator app, each of these codes can be used once to sign in. They won't be shown again."
        >
            <div
                className="mb-4"
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: '8px',
                    padding: '12px',
                    borderRadius: 'var(--af-radius-sm)',
                    backgroundColor: 'var(--af-surface)',
                    border: '1px solid var(--af-border)',
                }}
            >
                {codes.map((code) => (
                    <code key={code} style={{ fontSize: '14px', textAlign: 'center', userSelect: 'all' }}>
                        {code}
                    </code>
                ))}
            </div>

            <Link href={route('dashboard')} className="btn btn-primary w-100">
                I've saved them — continue
            </Link>
        </AuthShell>
    );
}
