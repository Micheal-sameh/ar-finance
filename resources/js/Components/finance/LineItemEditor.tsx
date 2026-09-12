import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/Components/ui/Button';
import { AccountPicker } from './AccountPicker';
import { MoneyDisplay } from './MoneyDisplay';

export interface LineItemInput {
    description: string;
    quantity: string;
    unit_price: string;
    tax_rate: string;
    account_id: number | null;
}

export function emptyLineItem(): LineItemInput {
    return { description: '', quantity: '1', unit_price: '', tax_rate: '0', account_id: null };
}

export function lineItemTotal(line: LineItemInput, withTax = false): number {
    const subtotal = (parseFloat(line.quantity) || 0) * (parseFloat(line.unit_price) || 0);

    return withTax ? subtotal * (1 + (parseFloat(line.tax_rate) || 0) / 100) : subtotal;
}

export interface LineItemEditorProps {
    lines: LineItemInput[];
    onChange: (lines: LineItemInput[]) => void;
    accountLabel?: string;
    currency?: string;
    /** Show a tax-rate column and include tax in the computed total — Bills need this, Purchase Orders don't. */
    showTax?: boolean;
    errors?: Record<string, string>;
}

/**
 * Description + qty + unit price + (optional tax) + account grid, with a
 * computed total. The untaxed mode is Purchase Order lines (no tax
 * concept there); showTax=true is Bill lines (Input VAT).
 */
export function LineItemEditor({ lines, onChange, accountLabel = 'Account', currency = 'EGP', showTax = false, errors = {} }: LineItemEditorProps) {
    function updateLine(index: number, patch: Partial<LineItemInput>) {
        onChange(lines.map((line, i) => (i === index ? { ...line, ...patch } : line)));
    }

    function addLine() {
        onChange([...lines, emptyLineItem()]);
    }

    function removeLine(index: number) {
        onChange(lines.filter((_, i) => i !== index));
    }

    const total = lines.reduce((sum, line) => sum + lineItemTotal(line, showTax), 0);

    return (
        <div>
            <div className="d-flex fw-medium mb-2" style={{ fontSize: '12px', color: 'var(--af-label)', textTransform: 'uppercase' }}>
                <div style={{ flex: '2 1 0' }}>Description</div>
                <div style={{ flex: '0 0 90px' }} className="text-end">Qty</div>
                <div style={{ flex: '1 1 0' }} className="text-end">Unit price</div>
                {showTax && <div style={{ flex: '0 0 90px' }} className="text-end">Tax %</div>}
                <div style={{ flex: '1.5 1 0' }} className="ps-2">{accountLabel}</div>
                <div style={{ flex: '1 1 0' }} className="text-end">Total</div>
                <div style={{ width: '36px' }} />
            </div>

            {lines.map((line, index) => (
                <div key={index} className="d-flex align-items-start gap-2 mb-2">
                    <div style={{ flex: '2 1 0' }}>
                        <input
                            type="text"
                            className="form-control"
                            style={{ borderRadius: 'var(--af-radius-sm)', fontSize: '14px' }}
                            value={line.description}
                            onChange={(e) => updateLine(index, { description: e.target.value })}
                            placeholder="Item or service"
                        />
                    </div>
                    <div style={{ flex: '0 0 90px' }}>
                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            className="form-control text-end"
                            style={{ borderRadius: 'var(--af-radius-sm)', fontSize: '14px' }}
                            value={line.quantity}
                            onChange={(e) => updateLine(index, { quantity: e.target.value })}
                        />
                    </div>
                    <div style={{ flex: '1 1 0' }}>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            className="form-control text-end"
                            style={{ borderRadius: 'var(--af-radius-sm)', fontSize: '14px' }}
                            value={line.unit_price}
                            onChange={(e) => updateLine(index, { unit_price: e.target.value })}
                            placeholder="0.00"
                        />
                    </div>
                    {showTax && (
                        <div style={{ flex: '0 0 90px' }}>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                className="form-control text-end"
                                style={{ borderRadius: 'var(--af-radius-sm)', fontSize: '14px' }}
                                value={line.tax_rate}
                                onChange={(e) => updateLine(index, { tax_rate: e.target.value })}
                            />
                        </div>
                    )}
                    <div style={{ flex: '1.5 1 0' }}>
                        <AccountPicker
                            value={line.account_id}
                            onChange={(accountId) => updateLine(index, { account_id: accountId })}
                            error={errors[`lines.${index}.account_id`]}
                            placeholder={accountLabel}
                        />
                    </div>
                    <div style={{ flex: '1 1 0' }} className="text-end pt-2">
                        <MoneyDisplay amount={lineItemTotal(line, showTax)} currency={currency} />
                    </div>
                    <div style={{ width: '36px' }} className="pt-1">
                        <button
                            type="button"
                            className="btn btn-sm p-1"
                            style={{ color: 'var(--af-danger)' }}
                            onClick={() => removeLine(index)}
                            disabled={lines.length <= 1}
                            aria-label="Remove line"
                        >
                            <Trash2 size={16} />
                        </button>
                    </div>
                </div>
            ))}

            <div className="d-flex align-items-center justify-content-between mt-2">
                <Button type="button" variant="outline" size="sm" leadingIcon={<Plus size={14} />} onClick={addLine}>
                    Add line
                </Button>
                <div style={{ fontSize: '15px', fontWeight: 600 }}>
                    Total: <MoneyDisplay amount={total} currency={currency} />
                </div>
            </div>
        </div>
    );
}
