import { Head, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { ReactNode } from 'react';
import { Card } from '@/Components/ui/Card';

interface AuthShellProps {
    title: string;
    heading: string;
    description?: ReactNode;
    children: ReactNode;
}

export function AuthShell({ title, heading, description, children }: AuthShellProps) {
    const { flash } = usePage<{ flash?: { error?: string | null; success?: string | null } }>().props;

    return (
        <div className="d-flex flex-column" style={{ minHeight: '100vh', background: 'var(--af-bg)' }}>
            <Head title={title} />

            <div className="d-flex align-items-center gap-2" style={{ padding: '16px 32px' }}>
                <div
                    className="d-flex align-items-center justify-content-center"
                    style={{ width: 28, height: 28, borderRadius: 8, backgroundColor: 'var(--af-navy)' }}
                >
                    <ShieldCheck size={16} color="var(--af-gold)" />
                </div>
                <span style={{ fontWeight: 700, fontSize: '15px', color: 'var(--af-navy)' }}>
                    Avarewase <span style={{ color: 'var(--af-gold)' }}>Finance</span>
                </span>
            </div>

            <div className="d-flex align-items-center justify-content-center flex-grow-1" style={{ padding: '24px' }}>
                <div style={{ width: '100%', maxWidth: '420px' }}>
                    <Card
                        style={{
                            borderRadius: 16,
                            boxShadow: 'var(--af-shadow), 0 16px 40px rgba(41,57,64,0.10)',
                            padding: '32px',
                        }}
                    >
                        <div
                            className="d-flex align-items-center justify-content-center mx-auto mb-3"
                            style={{ width: 44, height: 44, borderRadius: 12, backgroundColor: '#f4f0e7' }}
                        >
                            <ShieldCheck size={22} color="var(--af-gold)" />
                        </div>

                        <div className="text-center mb-4">
                            <h1 style={{ fontSize: '18px', fontWeight: 700, color: 'var(--af-navy)', marginBottom: '6px' }}>
                                {heading}
                            </h1>
                            {description && (
                                <p style={{ fontSize: '13px', color: 'var(--af-label)', margin: 0, lineHeight: 1.5 }}>
                                    {description}
                                </p>
                            )}
                        </div>

                        {flash?.success && (
                            <div className="mb-3" style={{ color: 'var(--af-success)', fontSize: '13px' }}>
                                {flash.success}
                            </div>
                        )}
                        {flash?.error && (
                            <div className="mb-3" style={{ color: 'var(--af-danger)', fontSize: '13px' }}>
                                {flash.error}
                            </div>
                        )}

                        {children}
                    </Card>
                </div>
            </div>
        </div>
    );
}
