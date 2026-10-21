import { Icon } from '@/Components/layout/Icon';
import { AccountPicker } from './AccountPicker';

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

export function lineSubtotal(line: InvoiceLineInput): number {
    return (parseFloat(line.quantity) || 0) * (parseFloat(line.unit_price) || 0);
}

export function lineTotal(line: InvoiceLineInput): number {
    const tax = parseFloat(line.tax_rate) || 0;

    return lineSubtotal(line) * (1 + tax / 100);
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
export function InvoiceLineEditor({ lines, onChange, currency = 'EGP', errors = {} }: InvoiceLineEditorProps) {
    function updateLine(index: number, patch: Partial<InvoiceLineInput>) {
        onChange(lines.map((line, i) => (i === index ? { ...line, ...patch } : line)));
    }

    function addLine() {
        onChange([...lines, emptyInvoiceLine()]);
    }

    function removeLine(index: number) {
        onChange(lines.filter((_, i) => i !== index));
    }

    return (
        <>
            <div className="line-header">
                <span>ITEM / DESCRIPTION</span>
                <span>QTY</span>
                <span>RATE</span>
                <span>TAX %</span>
                <span>AMOUNT</span>
                <span />
            </div>

            {lines.map((line, index) => (
                <div className="line-row" key={index}>
                    <div>
                        <input
                            type="text"
                            value={line.description}
                            onChange={(e) => updateLine(index, { description: e.target.value })}
                            placeholder="Item or service"
                            aria-label="Description"
                        />
                        <div style={{ marginTop: '6px' }}>
                            <AccountPicker
                                value={line.account_id}
                                onChange={(accountId) => updateLine(index, { account_id: accountId })}
                                error={errors[`lines.${index}.account_id`]}
                                placeholder="Revenue account"
                                filterType="revenue"
                            />
                        </div>
                    </div>
                    <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        value={line.quantity}
                        onChange={(e) => updateLine(index, { quantity: e.target.value })}
                        aria-label="Quantity"
                    />
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={line.unit_price}
                        onChange={(e) => updateLine(index, { unit_price: e.target.value })}
                        placeholder="0.00"
                        aria-label="Rate"
                    />
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        value={line.tax_rate}
                        onChange={(e) => updateLine(index, { tax_rate: e.target.value })}
                        aria-label="Tax rate"
                    />
                    <strong>
                        {currency} {lineTotal(line).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                    </strong>
                    <button
                        type="button"
                        className="icon-btn"
                        onClick={() => removeLine(index)}
                        disabled={lines.length <= 1}
                        aria-label="Remove line"
                    >
                        <Icon name="close" size={15} />
                    </button>
                </div>
            ))}

            <button type="button" className="add-line" onClick={addLine}>
                <Icon name="plus" size={16} />
                Add line item
            </button>
        </>
    );
}
