import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Sidebar } from '@/Components/layout/Sidebar';

interface FlashProps {
    success?: string | null;
    error?: string | null;
}

export function AppLayout({ children }: { children: ReactNode }) {
    const { flash } = usePage<{ flash: FlashProps }>().props;

    return (
        <div className="d-flex flex-column flex-lg-row" style={{ minHeight: '100vh', backgroundColor: 'var(--af-bg)' }}>
            <Sidebar />
            <main className="flex-grow-1 px-3 px-lg-4 py-3 py-lg-4" style={{ minWidth: 0 }}>
                {flash?.success && (
                    <div
                        className="mb-3 px-3 py-2"
                        style={{
                            borderRadius: 'var(--af-radius-sm)',
                            backgroundColor: '#EAF7EE',
                            border: '1px solid #BFE6CB',
                            color: 'var(--af-success)',
                            fontSize: '13px',
                        }}
                    >
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div
                        className="mb-3 px-3 py-2"
                        style={{
                            borderRadius: 'var(--af-radius-sm)',
                            backgroundColor: '#FCEBEB',
                            border: '1px solid #F4BFBF',
                            color: 'var(--af-danger)',
                            fontSize: '13px',
                        }}
                    >
                        {flash.error}
                    </div>
                )}
                {children}
            </main>
        </div>
    );
}
