import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/Components/ui/Button';
import { AccountPicker } from './AccountPicker';
import { MoneyDisplay } from './MoneyDisplay';

export interface InvoiceLineInput {
    description: string;
    quantity: string;
    unit_price: string;
    tax_rate: string;
    account_id: number | null;
}

export function emptyInvoiceLine(): InvoiceLineInput {
    return { description: '', quantity: '1', unit_price: '', tax_rate: '0', account_id: null };
}

export function lineTotal(line: InvoiceLineInput): number {
    const qty = parseFloat(line.quantity) || 0;
    const price = parseFloat(line.unit_price) || 0;
    const tax = parseFloat(line.tax_rate) || 0;

    return qty * price * (1 + tax / 100);
}

export interface InvoiceLineEditorProps {
    lines: InvoiceLineInput[];
    onChange: (lines: InvoiceLineInput[]) => void;
    currency?: string;
    errors?: Record<string, string>;
}

/**
 * Invoice line-item grid: description + qty + unit price + tax rate +
 * revenue account, with a computed line total. Tax is currently folded
 * into the revenue-account credit at posting time (see InvoiceService) —
 * shown here so the user sees the tax-inclusive total they're invoicing.
 */
export function InvoiceLineEditor({ lines, onChange, currency = 'USD', errors = {} }: InvoiceLineEditorProps) {
    function updateLine(index: number, patch: Partial<InvoiceLineInput>) {
        onChange(lines.map((line, i) => (i === index ? { ...line, ...patch } : line)));
    }

    function addLine() {
        onChange([...lines, emptyInvoiceLine()]);
    }

    function removeLine(index: number) {
        onChange(lines.filter((_, i) => i !== index));
    }

    const total = lines.reduce((sum, line) => sum + lineTotal(line), 0);

    return (
        <div>
            <div className="d-flex fw-medium mb-2" style={{ fontSize: '12px', color: 'var(--af-label)', textTransform: 'uppercase' }}>
                <div style={{ flex: '2 1 0' }}>Description</div>
                <div style={{ flex: '0 0 90px' }} className="text-end">Qty</div>
                <div style={{ flex: '1 1 0' }} className="text-end">Unit price</div>
                <div style={{ flex: '0 0 90px' }} className="text-end">Tax %</div>
                <div style={{ flex: '1.5 1 0' }}>Revenue account</div>
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
                    <div style={{ flex: '1.5 1 0' }}>
                        <AccountPicker
                            value={line.account_id}
                            onChange={(accountId) => updateLine(index, { account_id: accountId })}
                            error={errors[`lines.${index}.account_id`]}
                            placeholder="Revenue account"
                        />
                    </div>
                    <div style={{ flex: '1 1 0' }} className="text-end pt-2">
                        <MoneyDisplay amount={lineTotal(line)} currency={currency} />
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
