import { Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { AuthShell } from '@/Components/auth/AuthShell';
import { Button } from '@/Components/ui/Button';
import { InfoTip } from '@/Components/ui/InfoTip';

export default function TwoFactorChallenge() {
    const form = useForm({ code: '' });
    const [useRecovery, setUseRecovery] = useState(false);

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(route('two-factor.store'), { onFinish: () => form.reset('code') });
    }

    return (
        <AuthShell
            title="Two-factor authentication"
            heading="Two-factor authentication"
            description={
                useRecovery
                    ? 'Enter one of your unused recovery codes.'
                    : 'Enter the 6-digit code from your authenticator app.'
            }
        >
            <form onSubmit={submit}>
                <div className="mb-4">
                    <label htmlFor="code" className="d-block mb-1" style={{ fontSize: '12px', color: 'var(--af-label)' }}>
                        {useRecovery ? 'Recovery code' : 'Authentication code'} <InfoTip>The 6-digit code from your authenticator app. If you lost the app, use one of the recovery codes you saved when you set this up.</InfoTip>
                    </label>
                    <input
                        id="code"
                        type="text"
                        name="code"
                        inputMode={useRecovery ? 'text' : 'numeric'}
                        autoComplete="one-time-code"
                        autoFocus
                        value={form.data.code}
                        onChange={(e) => form.setData('code', e.target.value)}
                        className="form-control"
                        style={{
                            borderColor: form.errors.code ? 'var(--af-danger)' : 'var(--af-border)',
                            borderRadius: 'var(--af-radius-sm)',
                            fontSize: '16px',
                            letterSpacing: useRecovery ? undefined : '0.25em',
                            textAlign: 'center',
                        }}
                    />
                    {form.errors.code && (
                        <div style={{ color: 'var(--af-danger)', fontSize: '12px', marginTop: '4px' }}>{form.errors.code}</div>
                    )}
                </div>

                <Button type="submit" loading={form.processing} className="w-100 justify-content-center">
                    Verify
                </Button>
            </form>

            <div className="text-center mt-3 d-flex flex-column gap-1" style={{ fontSize: '13px' }}>
                <button
                    type="button"
                    className="btn btn-link p-0"
                    onClick={() => {
                        setUseRecovery((v) => !v);
                        form.reset('code');
                        form.clearErrors();
                    }}
                >
                    {useRecovery ? 'Use an authenticator code' : 'Use a recovery code'}
                </button>
                <Link href={route('login')}>Back to sign in</Link>
            </div>
        </AuthShell>
    );
}
