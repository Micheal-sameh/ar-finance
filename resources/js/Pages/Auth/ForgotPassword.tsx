import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AuthShell } from '@/Components/auth/AuthShell';
import { Button } from '@/Components/ui/Button';

export default function ForgotPassword() {
    const form = useForm({ email: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(route('password.email'), { onSuccess: () => form.reset('email') });
    }

    return (
        <AuthShell
            title="Forgot password"
            heading="Forgot your password?"
            description="Enter the email on your account and we'll send you a link to reset it."
        >
            <form onSubmit={submit}>
                <div className="mb-4">
                    <label htmlFor="email" className="d-block mb-1" style={{ fontSize: '12px', color: 'var(--af-label)' }}>
                        Email
                    </label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        autoComplete="email"
                        autoFocus
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        className="form-control"
                        style={{
                            borderColor: form.errors.email ? 'var(--af-danger)' : 'var(--af-border)',
                            borderRadius: 'var(--af-radius-sm)',
                            fontSize: '14px',
                        }}
                    />
                    {form.errors.email && (
                        <div style={{ color: 'var(--af-danger)', fontSize: '12px', marginTop: '4px' }}>{form.errors.email}</div>
                    )}
                </div>

                <Button type="submit" loading={form.processing} className="w-100 justify-content-center">
                    Send reset link
                </Button>
            </form>

            <div className="text-center mt-3" style={{ fontSize: '13px' }}>
                <Link href={route('login')}>Back to sign in</Link>
            </div>
        </AuthShell>
    );
}
