import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AuthShell } from '@/Components/auth/AuthShell';
import { Button } from '@/Components/ui/Button';

interface Props {
    token: string;
    email: string;
}

export default function ResetPassword({ token, email }: Props) {
    const form = useForm({ token, email, password: '', password_confirmation: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(route('password.update'), { onFinish: () => form.reset('password', 'password_confirmation') });
    }

    const fieldStyle = (hasError: boolean) => ({
        borderColor: hasError ? 'var(--af-danger)' : 'var(--af-border)',
        borderRadius: 'var(--af-radius-sm)',
        fontSize: '14px',
    });

    return (
        <AuthShell title="Reset password" heading="Choose a new password" description="Use at least 8 characters.">
            <form onSubmit={submit}>
                <div className="mb-3">
                    <label htmlFor="email" className="d-block mb-1" style={{ fontSize: '12px', color: 'var(--af-label)' }}>
                        Email
                    </label>
                    <input
                        id="email"
                        type="email"
                        autoComplete="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        className="form-control"
                        style={fieldStyle(!!form.errors.email)}
                    />
                    {form.errors.email && (
                        <div style={{ color: 'var(--af-danger)', fontSize: '12px', marginTop: '4px' }}>{form.errors.email}</div>
                    )}
                </div>

                <div className="mb-3">
                    <label htmlFor="password" className="d-block mb-1" style={{ fontSize: '12px', color: 'var(--af-label)' }}>
                        New password
                    </label>
                    <input
                        id="password"
                        type="password"
                        autoComplete="new-password"
                        autoFocus
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                        className="form-control"
                        style={fieldStyle(!!form.errors.password)}
                    />
                    {form.errors.password && (
                        <div style={{ color: 'var(--af-danger)', fontSize: '12px', marginTop: '4px' }}>{form.errors.password}</div>
                    )}
                </div>

                <div className="mb-4">
                    <label htmlFor="password_confirmation" className="d-block mb-1" style={{ fontSize: '12px', color: 'var(--af-label)' }}>
                        Confirm password
                    </label>
                    <input
                        id="password_confirmation"
                        type="password"
                        autoComplete="new-password"
                        value={form.data.password_confirmation}
                        onChange={(e) => form.setData('password_confirmation', e.target.value)}
                        className="form-control"
                        style={fieldStyle(false)}
                    />
                </div>

                <Button type="submit" loading={form.processing} className="w-100 justify-content-center">
                    Reset password
                </Button>
            </form>

            <div className="text-center mt-3" style={{ fontSize: '13px' }}>
                <Link href={route('login')}>Back to sign in</Link>
            </div>
        </AuthShell>
    );
}
