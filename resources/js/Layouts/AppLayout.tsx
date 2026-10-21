import { usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Sidebar } from '@/Components/layout/Sidebar';
import { Topbar } from '@/Components/layout/Topbar';

interface FlashProps {
    success?: string | null;
    error?: string | null;
}

export function AppLayout({ children }: { children: ReactNode }) {
    const { flash } = usePage<{ flash: FlashProps }>().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(() => {
        try {
            return localStorage.getItem('af.sidebarCollapsed') === '1';
        } catch {
            return false;
        }
    });

    function toggleCollapsed() {
        setCollapsed((previous) => {
            try {
                localStorage.setItem('af.sidebarCollapsed', previous ? '0' : '1');
            } catch {
                // storage unavailable: the toggle still works for this page view
            }

            return !previous;
        });
    }

    return (
        <div className="fg-app">
            <div className={`app-shell ${collapsed ? 'collapsed' : ''}`}>
                <Sidebar open={sidebarOpen} onClose={() => setSidebarOpen(false)} collapsed={collapsed} onToggleCollapsed={toggleCollapsed} />
                <div className="workspace">
                    <Topbar onOpenSidebar={() => setSidebarOpen(true)} />
                    <main>
                        {flash?.success && (
                            <div
                                className="mb-3 px-3 py-2"
                                style={{
                                    borderRadius: 'var(--af-radius-sm)',
                                    backgroundColor: 'var(--green-soft)',
                                    border: '1px solid #c6d9ce',
                                    color: 'var(--green)',
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
                                    backgroundColor: 'var(--red-soft)',
                                    border: '1px solid #e6caca',
                                    color: 'var(--red)',
                                    fontSize: '13px',
                                }}
                            >
                                {flash.error}
                            </div>
                        )}
                        {children}
                    </main>
                </div>
            </div>
        </div>
    );
}
