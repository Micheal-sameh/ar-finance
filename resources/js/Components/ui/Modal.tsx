import { Dialog, Transition } from '@headlessui/react';
import { X } from 'lucide-react';
import { Fragment, type ReactNode } from 'react';

export interface ModalProps {
    open: boolean;
    onClose: () => void;
    title?: string;
    children: ReactNode;
    footer?: ReactNode;
}

export function Modal({ open, onClose, title, children, footer }: ModalProps) {
    return (
        <Transition show={open} as={Fragment}>
            <Dialog onClose={onClose} className="position-relative" style={{ zIndex: 1050 }}>
                <Transition.Child
                    as={Fragment}
                    enter="fade-enter"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="fade-leave"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div
                        className="position-fixed top-0 start-0 w-100 h-100"
                        style={{ backgroundColor: 'rgba(15, 23, 42, 0.45)' }}
                        aria-hidden="true"
                    />
                </Transition.Child>

                <div className="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center p-3">
                    <Dialog.Panel
                        className="w-100"
                        style={{
                            maxWidth: '520px',
                            backgroundColor: 'var(--af-surface)',
                            borderRadius: 'var(--af-radius)',
                            boxShadow: '0 20px 25px -5px rgb(15 23 42 / 0.15)',
                        }}
                    >
                        <div
                            className="d-flex align-items-center justify-content-between px-4 py-3"
                            style={{ borderBottom: '1px solid var(--af-border)' }}
                        >
                            <Dialog.Title style={{ fontSize: '16px', fontWeight: 600, color: 'var(--af-navy)' }}>
                                {title}
                            </Dialog.Title>
                            <button
                                type="button"
                                onClick={onClose}
                                className="btn btn-sm p-1"
                                aria-label="Close"
                                style={{ color: 'var(--af-label)' }}
                            >
                                <X size={18} />
                            </button>
                        </div>

                        <div className="px-4 py-3">{children}</div>

                        {footer && (
                            <div
                                className="d-flex justify-content-end gap-2 px-4 py-3"
                                style={{ borderTop: '1px solid var(--af-border)' }}
                            >
                                {footer}
                            </div>
                        )}
                    </Dialog.Panel>
                </div>
            </Dialog>
        </Transition>
    );
}
