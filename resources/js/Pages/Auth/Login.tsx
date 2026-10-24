import { Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff, Lock, User } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AuthShell } from '@/Components/auth/AuthShell';
import { Button } from '@/Components/ui/Button';

export default function Login() {
    const form = useForm({ identifier: '', password: '', remember: false });
    const [showPassword, setShowPassword] = useState(false);

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(route('login.store'), { onFinish: () => form.reset('password') });
    }

    return (
        <AuthShell
            title="Log in"
            heading="Welcome back"
            description="Sign in with your email or phone number and password."
        >
            <form onSubmit={submit}>
                <div className="mb-3">
                    <label htmlFor="identifier" className="d-block mb-1" style={{ fontSize: '12px', color: 'var(--af-label)' }}>
                        Email or phone number
                    </label>
                    <div className="position-relative">
                        <User
                            size={15}
                            color="var(--af-label)"
                            style={{ position: 'absolute', left: 10, top: '50%', transform: 'translateY(-50%)' }}
                        />
                        <input
                            id="identifier"
                            type="text"
                            name="identifier"
                            autoComplete="username"
                            autoFocus
                            value={form.data.identifier}
                            onChange={(e) => form.setData('identifier', e.target.value)}
                            className="form-control"
                            style={{
                                borderColor: form.errors.identifier ? 'var(--af-danger)' : 'var(--af-border)',
                                borderRadius: 'var(--af-radius-sm)',
                                fontSize: '14px',
                                paddingLeft: 34,
                            }}
                        />
                    </div>
                    {form.errors.identifier && (
                        <div style={{ color: 'var(--af-danger)', fontSize: '12px', marginTop: '4px' }}>{form.errors.identifier}</div>
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
                        <div style={{ color: 'var(--af-danger)', fontSize: '12px', marginTop: '4px' }}>{form.errors.password}</div>
                    )}
                </div>

                <div className="d-flex align-items-center justify-content-between mb-4">
                    <div className="form-check mb-0">
                        <input
                            id="remember"
                            type="checkbox"
                            className="form-check-input"
                            checked={form.data.remember}
                            onChange={(e) => form.setData('remember', e.target.checked)}
                        />
                        <label htmlFor="remember" className="form-check-label" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                            Keep me signed in
                        </label>
                    </div>
                    <Link href={route('password.request')} style={{ fontSize: '13px' }}>
                        Forgot password?
                    </Link>
                </div>

                <Button type="submit" loading={form.processing} className="w-100 justify-content-center">
                    Continue
                </Button>
            </form>
        </AuthShell>
    );
}
