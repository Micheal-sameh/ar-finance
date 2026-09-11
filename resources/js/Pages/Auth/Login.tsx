import { Head, useForm } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { FormEvent } from 'react';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Input } from '@/Components/ui/Input';

interface LoginProps {
    devLoginEnabled: boolean;
}

export default function Login({ devLoginEnabled }: LoginProps) {
    const form = useForm({ email: '', password: '' });

    function submitDevLogin(e: FormEvent) {
        e.preventDefault();
        form.post(route('dev-login'));
    }

    return (
        <div
            className="d-flex align-items-center justify-content-center"
            style={{ minHeight: '100vh', backgroundColor: 'var(--af-primary)', padding: '24px' }}
        >
            <Head title="Log in" />

            <div style={{ width: '100%', maxWidth: '380px' }}>
                <div className="text-center mb-4" style={{ color: '#fff', fontWeight: 700, fontSize: '22px' }}>
                    Avarewase <span style={{ color: 'var(--af-gold)' }}>Finance</span>
                </div>

                <Card>
                    <div className="text-center mb-4">
                        <h1 style={{ fontSize: '17px', fontWeight: 600, color: 'var(--af-navy)', marginBottom: '4px' }}>
                            Welcome back
                        </h1>
                        <p style={{ fontSize: '13px', color: 'var(--af-label)', margin: 0 }}>
                            Sign in with your Avarewase account to continue.
                        </p>
                    </div>

                    <a
                        href={route('avarewase.login')}
                        className="d-flex align-items-center justify-content-center gap-2 text-decoration-none fw-medium w-100"
                        style={{
                            backgroundColor: 'var(--af-primary)',
                            color: '#fff',
                            borderRadius: 'var(--af-radius-sm)',
                            padding: '10px 16px',
                            fontSize: '14px',
                        }}
                    >
                        Continue with Avarewase
                        <ArrowRight size={16} />
                    </a>

                    {devLoginEnabled && (
                        <>
                            <div className="d-flex align-items-center gap-2 my-4">
                                <div style={{ flex: 1, height: 1, backgroundColor: 'var(--af-border)' }} />
                                <span style={{ fontSize: '11px', color: 'var(--af-label)' }}>DEV ONLY</span>
                                <div style={{ flex: 1, height: 1, backgroundColor: 'var(--af-border)' }} />
                            </div>

                            <form onSubmit={submitDevLogin}>
                                <div className="mb-3">
                                    <Input
                                        type="email"
                                        name="email"
                                        label="Email"
                                        autoComplete="username"
                                        value={form.data.email}
                                        onChange={(e) => form.setData('email', e.target.value)}
                                        error={form.errors.email}
                                    />
                                </div>
                                <div className="mb-3">
                                    <Input
                                        type="password"
                                        name="password"
                                        label="Password"
                                        autoComplete="current-password"
                                        value={form.data.password}
                                        onChange={(e) => form.setData('password', e.target.value)}
                                        error={form.errors.password}
                                    />
                                </div>
                                <Button type="submit" variant="outline" loading={form.processing} className="w-100 justify-content-center">
                                    Sign in with password
                                </Button>
                            </form>
                        </>
                    )}
                </Card>

                <p className="text-center mt-4" style={{ fontSize: '12px', color: 'rgba(255,255,255,0.6)' }}>
                    Identity and roles are managed centrally by Avarewase Auth.
                </p>
            </div>
        </div>
    );
}
