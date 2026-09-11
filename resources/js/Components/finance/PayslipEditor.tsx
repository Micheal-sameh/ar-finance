import { MoneyDisplay } from './MoneyDisplay';
import type { Employee } from '@/types/finance';

export interface PayslipInput {
    employee_id: number;
    gross_pay: string;
    deductions: string;
}

export function netPay(payslip: PayslipInput): number {
    return (parseFloat(payslip.gross_pay) || 0) - (parseFloat(payslip.deductions) || 0);
}

export interface PayslipEditorProps {
    employees: Employee[];
    payslips: PayslipInput[];
    onChange: (payslips: PayslipInput[]) => void;
    errors?: Record<string, string>;
}

/**
 * One row per employee selected into a payroll run — gross pay defaults
 * from the employee's salary, deductions are editable, net pay is
 * computed. Unlike JournalLineEditor/LineItemEditor this isn't an
 * add/remove list — it's driven by which employees are toggled in.
 */
export function PayslipEditor({ employees, payslips, onChange, errors = {} }: PayslipEditorProps) {
    function isIncluded(employeeId: number): boolean {
        return payslips.some((p) => p.employee_id === employeeId);
    }

    function toggle(employee: Employee) {
        if (isIncluded(employee.id)) {
            onChange(payslips.filter((p) => p.employee_id !== employee.id));
        } else {
            onChange([...payslips, { employee_id: employee.id, gross_pay: employee.salary, deductions: '0' }]);
        }
    }

    function updatePayslip(employeeId: number, patch: Partial<PayslipInput>) {
        onChange(payslips.map((p) => (p.employee_id === employeeId ? { ...p, ...patch } : p)));
    }

    const totalGross = payslips.reduce((sum, p) => sum + (parseFloat(p.gross_pay) || 0), 0);
    const totalDeductions = payslips.reduce((sum, p) => sum + (parseFloat(p.deductions) || 0), 0);
    const totalNet = payslips.reduce((sum, p) => sum + netPay(p), 0);

    return (
        <div>
            <div className="d-flex fw-medium mb-2" style={{ fontSize: '12px', color: 'var(--af-label)', textTransform: 'uppercase' }}>
                <div style={{ width: '28px' }} />
                <div style={{ flex: '2 1 0' }}>Employee</div>
                <div style={{ flex: '1 1 0' }} className="text-end">Gross pay</div>
                <div style={{ flex: '1 1 0' }} className="text-end">Deductions</div>
                <div style={{ flex: '1 1 0' }} className="text-end">Net pay</div>
            </div>

            {employees.map((employee) => {
                const included = isIncluded(employee.id);
                const payslip = payslips.find((p) => p.employee_id === employee.id);

                return (
                    <div key={employee.id} className="d-flex align-items-center gap-2 mb-2">
                        <div style={{ width: '28px' }}>
                            <input
                                type="checkbox"
                                className="form-check-input"
                                checked={included}
                                onChange={() => toggle(employee)}
                                aria-label={`Include ${employee.name}`}
                            />
                        </div>
                        <div style={{ flex: '2 1 0' }}>{employee.name}</div>
                        <div style={{ flex: '1 1 0' }}>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                className="form-control text-end"
                                style={{ borderRadius: 'var(--af-radius-sm)', fontSize: '14px' }}
                                value={payslip?.gross_pay ?? ''}
                                disabled={!included}
                                onChange={(e) => updatePayslip(employee.id, { gross_pay: e.target.value })}
                            />
                        </div>
                        <div style={{ flex: '1 1 0' }}>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                className="form-control text-end"
                                style={{ borderRadius: 'var(--af-radius-sm)', fontSize: '14px' }}
                                value={payslip?.deductions ?? ''}
                                disabled={!included}
                                onChange={(e) => updatePayslip(employee.id, { deductions: e.target.value })}
                            />
                        </div>
                        <div style={{ flex: '1 1 0' }} className="text-end pt-2">
                            {included && payslip ? <MoneyDisplay amount={netPay(payslip)} /> : <span style={{ color: 'var(--af-label)' }}>—</span>}
                        </div>
                    </div>
                );
            })}
            {errors.payslips && <div style={{ color: 'var(--af-danger)', fontSize: '12px', marginTop: '4px' }}>{errors.payslips}</div>}

            <div className="d-flex justify-content-end gap-4 mt-3 pt-2" style={{ borderTop: '1px solid var(--af-border)', fontSize: '14px' }}>
                <div>Gross: <strong><MoneyDisplay amount={totalGross} /></strong></div>
                <div>Deductions: <strong><MoneyDisplay amount={totalDeductions} /></strong></div>
                <div>Net: <strong><MoneyDisplay amount={totalNet} /></strong></div>
            </div>
        </div>
    );
}
