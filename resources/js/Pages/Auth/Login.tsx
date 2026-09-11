import { Head, useForm } from '@inertiajs/react';
import { ArrowRight, Eye, EyeOff, Lock, Mail, ShieldCheck } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';

interface LoginProps {
    devLoginEnabled: boolean;
}

const INDIGO = '#6366f1';
const INDIGO_DARK = '#4f46e5';

export default function Login({ devLoginEnabled }: LoginProps) {
    const form = useForm({ email: '', password: '' });
    const [showPassword, setShowPassword] = useState(false);

    function submitDevLogin(e: FormEvent) {
        e.preventDefault();
        form.post(route('dev-login'));
    }

    return (
        <div
            className="d-flex flex-column"
            style={{
                minHeight: '100vh',
                background: 'linear-gradient(135deg, #eef1fd 0%, #f6f7fc 50%, #eef2fb 100%)',
            }}
        >
            <Head title="Log in" />

            <div className="d-flex align-items-center justify-content-between" style={{ padding: '16px 32px' }}>
                <div className="d-flex align-items-center gap-2">
                    <div
                        className="d-flex align-items-center justify-content-center"
                        style={{ width: 28, height: 28, borderRadius: 8, backgroundColor: INDIGO }}
                    >
                        <ShieldCheck size={16} color="#fff" />
                    </div>
                    <span style={{ fontWeight: 700, fontSize: '15px', color: 'var(--af-navy)' }}>
                        Avarewase <span style={{ color: 'var(--af-gold)' }}>Finance</span>
                    </span>
                </div>

                <div className="d-flex align-items-center gap-2">
                    <span
                        className="d-flex align-items-center gap-1"
                        style={{
                            fontSize: '11px',
                            color: 'var(--af-label)',
                            backgroundColor: '#fff',
                            border: '1px solid var(--af-border)',
                            borderRadius: 999,
                            padding: '4px 10px',
                        }}
                    >
                        <span style={{ width: 6, height: 6, borderRadius: '50%', backgroundColor: '#22c55e', display: 'inline-block' }} />
                        SSO Shield 2.4 Active
                    </span>
                    <span
                        className="d-flex align-items-center gap-1"
                        style={{
                            fontSize: '11px',
                            color: 'var(--af-label)',
                            backgroundColor: '#fff',
                            border: '1px solid var(--af-border)',
                            borderRadius: 999,
                            padding: '4px 10px',
                        }}
                    >
                        <Lock size={11} />
                        FIPS 140-2 Compliant
                    </span>
                </div>
            </div>

            <div className="d-flex align-items-center justify-content-center flex-grow-1" style={{ padding: '24px' }}>
                <div style={{ width: '100%', maxWidth: '420px' }}>
                    <Card
                        style={{
                            borderRadius: 16,
                            boxShadow: '0 0 0 4px rgba(99,102,241,0.08), 0 16px 40px rgba(30,41,90,0.12)',
                            padding: '32px',
                        }}
                    >
                        <div
                            className="d-flex align-items-center justify-content-center mx-auto mb-3"
                            style={{ width: 44, height: 44, borderRadius: 12, backgroundColor: 'rgba(99,102,241,0.12)' }}
                        >
                            <ShieldCheck size={22} color={INDIGO} />
                        </div>

                        <div className="text-center mb-4">
                            <h1 style={{ fontSize: '18px', fontWeight: 700, color: 'var(--af-navy)', marginBottom: '6px' }}>
                                Welcome back
                            </h1>
                            <p style={{ fontSize: '13px', color: 'var(--af-label)', margin: 0, lineHeight: 1.5 }}>
                                Sign in with your centralized <strong>Avarewase enterprise account</strong> to access
                                General Ledger, AP/AR &amp; Treasury suites.
                            </p>
                        </div>

                        <a
                            href={route('avarewase.login')}
                            className="d-flex align-items-center justify-content-center gap-2 text-decoration-none fw-medium w-100 mb-3"
                            style={{
                                background: `linear-gradient(135deg, ${INDIGO} 0%, ${INDIGO_DARK} 100%)`,
                                color: '#fff',
                                borderRadius: 'var(--af-radius-sm)',
                                padding: '11px 16px',
                                fontSize: '14px',
                            }}
                        >
                            <ShieldCheck size={16} />
                            Continue with Avarewase SSO
                            <ArrowRight size={16} />
                        </a>

                        {devLoginEnabled && (
                            <>
                                <div className="d-flex align-items-center gap-2 mb-4">
                                    <div style={{ flex: 1, height: 1, backgroundColor: 'var(--af-border)' }} />
                                    <span style={{ fontSize: '10px', letterSpacing: '0.05em', color: 'var(--af-label)' }}>
                                        DIRECT AUTH / DEV MODE
                                    </span>
                                    <div style={{ flex: 1, height: 1, backgroundColor: 'var(--af-border)' }} />
                                </div>

                                <form onSubmit={submitDevLogin}>
                                    <div className="mb-3">
                                        <label htmlFor="email" className="d-block mb-1" style={{ fontSize: '12px', color: 'var(--af-label)' }}>
                                            Work Email Address
                                        </label>
                                        <div className="position-relative">
                                            <Mail
                                                size={15}
                                                color="var(--af-label)"
                                                style={{ position: 'absolute', left: 10, top: '50%', transform: 'translateY(-50%)' }}
                                            />
                                            <input
                                                id="email"
                                                type="email"
                                                name="email"
                                                autoComplete="username"
                                                value={form.data.email}
                                                onChange={(e) => form.setData('email', e.target.value)}
                                                className="form-control"
                                                style={{
                                                    borderColor: form.errors.email ? 'var(--af-danger)' : 'var(--af-border)',
                                                    borderRadius: 'var(--af-radius-sm)',
                                                    fontSize: '14px',
                                                    paddingLeft: 34,
                                                }}
                                            />
                                        </div>
                                        {form.errors.email && (
                                            <div style={{ color: 'var(--af-danger)', fontSize: '12px', marginTop: '4px' }}>
                                                {form.errors.email}
                                            </div>
                                        )}
                                    </div>

                                    <div className="mb-3">
                                        <label htmlFor="password" className="d-block mb-1" style={{ fontSize: '12px', color: 'var(--af-label)' }}>
                                            Password
                                        </label>
                                        <div className="position-relative">
                                            <Lock
                                                size={15}
                                                color="var(--af-label)"
                                                style={{ position: 'absolute', left: 10, top: '50%', transform: 'translateY(-50%)' }}
                                            />
                                            <input
                                                id="password"
                                                type={showPassword ? 'text' : 'password'}
                                                name="password"
                                                autoComplete="current-password"
                                                value={form.data.password}
                                                onChange={(e) => form.setData('password', e.target.value)}
                                                className="form-control"
                                                style={{
                                                    borderColor: form.errors.password ? 'var(--af-danger)' : 'var(--af-border)',
                                                    borderRadius: 'var(--af-radius-sm)',
                                                    fontSize: '14px',
                                                    paddingLeft: 34,
                                                    paddingRight: 34,
                                                }}
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setShowPassword((v) => !v)}
                                                className="btn p-0 border-0 bg-transparent"
                                                style={{ position: 'absolute', right: 10, top: '50%', transform: 'translateY(-50%)', color: 'var(--af-label)' }}
                                                aria-label={showPassword ? 'Hide password' : 'Show password'}
                                            >
                                                {showPassword ? <EyeOff size={15} /> : <Eye size={15} />}
                                            </button>
                                        </div>
                                        {form.errors.password && (
                                            <div style={{ color: 'var(--af-danger)', fontSize: '12px', marginTop: '4px' }}>
                                                {form.errors.password}
                                            </div>
                                        )}
                                    </div>

                                    <Button type="submit" variant="outline" loading={form.processing} className="w-100 justify-content-center">
                                        Sign in with password
                                    </Button>
                                </form>
                            </>
                        )}
                    </Card>

                    <div className="text-center mt-4">
                        <p style={{ fontSize: '12px', color: 'var(--af-navy)', margin: 0, fontWeight: 500 }}>
                            Identity and roles are managed centrally by Avarewase Auth.
                        </p>
                        <p style={{ fontSize: '11px', color: 'var(--af-label)', margin: '4px 0 0' }}>
                            End-to-End Encrypted • Active Session Auditing • Zero Trust Architecture
                        </p>
                    </div>
                </div>
            </div>

            <div
                className="d-flex align-items-center justify-content-between"
                style={{ padding: '12px 32px', fontSize: '11px', color: 'var(--af-label)' }}
            >
                <div className="d-flex align-items-center gap-3">
                    <span>Tenant: Avarewase Corp (Holding)</span>
                    <span>Region: Cairo North (eg-cai-01)</span>
                </div>
                <div className="d-flex align-items-center gap-3">
                    <span className="d-flex align-items-center gap-1">
                        <span style={{ width: 6, height: 6, borderRadius: '50%', backgroundColor: '#22c55e', display: 'inline-block' }} />
                        SOC2 / EAS 8 Audit
                    </span>
                    <span>System Status</span>
                    <span>Finance Helpdesk</span>
                    <span>Security Policy</span>
                </div>
            </div>
        </div>
    );
}
