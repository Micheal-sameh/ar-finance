import { Link, useForm } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import { FormEvent } from 'react';
import { AuthShell } from '@/Components/auth/AuthShell';
import { Button } from '@/Components/ui/Button';
import { InfoTip } from '@/Components/ui/InfoTip';

interface Props {
    secret: string;
    otpauthUri: string;
}

export default function TwoFactorSetup({ secret, otpauthUri }: Props) {
    const form = useForm({ code: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(route('two-factor.store'), { onFinish: () => form.reset('code') });
    }

    return (
        <AuthShell
            title="Set up two-factor authentication"
            heading="Set up two-factor authentication"
            description="Your account requires a second step at sign-in. Add it to an authenticator app (Google Authenticator, Authy, 1Password…), then enter the code it shows."
        >
            <div className="mb-3">
                <div style={{ fontSize: '12px', color: 'var(--af-label)', marginBottom: '8px' }}>
                    1. Scan this QR code with your authenticator app
                </div>
                <div className="d-flex justify-content-center mb-3">
                    <div style={{ padding: 12, backgroundColor: '#fff', borderRadius: 8, border: '1px solid var(--af-border)' }}>
                        <QRCodeSVG value={otpauthUri} size={176} />
                    </div>
                </div>
                <div style={{ fontSize: '12px', color: 'var(--af-label)', marginBottom: '4px' }}>
                    Can't scan? Enter this setup key instead
                </div>
                <code
                    className="d-block text-center"
                    style={{
                        padding: '10px',
                        borderRadius: 'var(--af-radius-sm)',
                        backgroundColor: 'var(--af-surface)',
                        border: '1px solid var(--af-border)',
                        fontSize: '14px',
                        letterSpacing: '0.1em',
                        wordBreak: 'break-all',
                        userSelect: 'all',
                    }}
                >
                    {secret}
                </code>
            </div>

            <form onSubmit={submit}>
                <div className="mb-4">
                    <label htmlFor="code" className="d-block mb-1" style={{ fontSize: '12px', color: 'var(--af-label)' }}>
                        2. Enter the 6-digit code <InfoTip>Open your authenticator app and type the current 6-digit code to prove it is set up correctly.</InfoTip>
                    </label>
                    <input
                        id="code"
                        type="text"
                        name="code"
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        autoFocus
                        value={form.data.code}
                        onChange={(e) => form.setData('code', e.target.value)}
                        className="form-control"
                        style={{
                            borderColor: form.errors.code ? 'var(--af-danger)' : 'var(--af-border)',
                            borderRadius: 'var(--af-radius-sm)',
                            fontSize: '16px',
                            letterSpacing: '0.25em',
                            textAlign: 'center',
                        }}
                    />
                    {form.errors.code && (
                        <div style={{ color: 'var(--af-danger)', fontSize: '12px', marginTop: '4px' }}>{form.errors.code}</div>
                    )}
                </div>

                <Button type="submit" loading={form.processing} className="w-100 justify-content-center">
                    Enable and sign in
                </Button>
            </form>

            <div className="text-center mt-3" style={{ fontSize: '13px' }}>
                <Link href={route('login')}>Back to sign in</Link>
            </div>
        </AuthShell>
    );
}
