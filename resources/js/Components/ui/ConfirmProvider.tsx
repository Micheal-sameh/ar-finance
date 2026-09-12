import { createContext, useCallback, useContext, useState, type ReactNode } from 'react';
import { Button } from './Button';
import { Modal } from './Modal';

export interface ConfirmOptions {
    title?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    /** Use 'danger' for destructive actions (delete, void, cancel). */
    variant?: 'danger' | 'primary';
}

type ConfirmFn = (message: string, options?: ConfirmOptions) => Promise<boolean>;

interface PendingConfirm {
    message: string;
    options: ConfirmOptions;
    resolve: (value: boolean) => void;
}

const ConfirmContext = createContext<ConfirmFn | null>(null);

export function ConfirmProvider({ children }: { children: ReactNode }) {
    const [pending, setPending] = useState<PendingConfirm | null>(null);

    const confirm = useCallback<ConfirmFn>((message, options = {}) => {
        return new Promise<boolean>((resolve) => {
            setPending({ message, options, resolve });
        });
    }, []);

    function settle(result: boolean) {
        pending?.resolve(result);
        setPending(null);
    }

    return (
        <ConfirmContext.Provider value={confirm}>
            {children}
            <Modal
                open={pending !== null}
                onClose={() => settle(false)}
                title={pending?.options.title ?? 'Please confirm'}
                footer={
                    <>
                        <Button variant="outline" size="sm" onClick={() => settle(false)}>
                            {pending?.options.cancelLabel ?? 'Cancel'}
                        </Button>
                        <Button
                            variant={pending?.options.variant === 'primary' ? 'primary' : 'danger'}
                            size="sm"
                            onClick={() => settle(true)}
                        >
                            {pending?.options.confirmLabel ?? 'Confirm'}
                        </Button>
                    </>
                }
            >
                {pending?.message}
            </Modal>
        </ConfirmContext.Provider>
    );
}

/** Promise-based replacement for `window.confirm`: `if (await confirm('...')) { ... }` */
export function useConfirm(): ConfirmFn {
    const ctx = useContext(ConfirmContext);
    if (!ctx) {
        throw new Error('useConfirm must be used within a ConfirmProvider');
    }
    return ctx;
}
