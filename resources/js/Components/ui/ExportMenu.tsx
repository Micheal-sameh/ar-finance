import { Menu } from '@headlessui/react';
import { ChevronDown, Download } from 'lucide-react';
import type { AnchorHTMLAttributes } from 'react';

export interface ExportMenuOption extends AnchorHTMLAttributes<HTMLAnchorElement> {
    label: string;
}

interface ExportMenuProps {
    label?: string;
    options: ExportMenuOption[];
}

/**
 * Collapses multiple export links (e.g. Excel + PDF) into a single dropdown
 * — styled like ExportButton's outline look, but with a menu instead of a
 * plain <a>, since more than one export format shouldn't mean more than one
 * button in the page header.
 */
export function ExportMenu({ label = 'Export', options }: ExportMenuProps) {
    return (
        <Menu as="div" className="position-relative d-inline-block">
            <Menu.Button
                className="btn d-inline-flex align-items-center gap-2 fw-medium"
                style={{
                    borderRadius: 'var(--af-radius-sm)',
                    border: '1px solid var(--af-border)',
                    lineHeight: 1.2,
                    backgroundColor: 'transparent',
                    color: 'var(--af-text)',
                    padding: '8px 16px',
                    fontSize: '14px',
                }}
            >
                <Download size={16} />
                {label}
                <ChevronDown size={14} />
            </Menu.Button>

            <Menu.Items
                className="position-absolute mt-1 py-1"
                style={{
                    zIndex: 20,
                    minWidth: '180px',
                    right: 0,
                    backgroundColor: 'var(--af-surface)',
                    border: '1px solid var(--af-border)',
                    borderRadius: 'var(--af-radius-sm)',
                    boxShadow: '0 10px 15px -3px rgb(15 23 42 / 0.1)',
                }}
            >
                {options.map(({ label: optionLabel, ...rest }) => (
                    <Menu.Item key={optionLabel}>
                        {({ active }) => (
                            <a
                                className="d-block px-3 py-2 text-decoration-none"
                                style={{
                                    fontSize: '14px',
                                    color: 'var(--af-text)',
                                    backgroundColor: active ? '#EFF4FE' : 'transparent',
                                }}
                                {...rest}
                            >
                                {optionLabel}
                            </a>
                        )}
                    </Menu.Item>
                ))}
            </Menu.Items>
        </Menu>
    );
}
