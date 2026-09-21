<?php

namespace App\Services;

use App\DTOs\CreateJournalEntryData;
use App\DTOs\CreatePayrollRunData;
use App\DTOs\JournalLineData;
use App\DTOs\PayslipData;
use App\Enums\JournalSourceType;
use App\Models\PayrollRun;
use App\Repositories\Contracts\PayrollRunRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Same draft → approve → markPaid shape as Bills/Invoices/Expenses:
 * approve() recognizes the expense, markPaid() settles the liability —
 * two separate journal entries.
 */
class PayrollRunService
{
    public function __construct(
        private readonly PayrollRunRepositoryInterface $payrollRuns,
        private readonly JournalService $journals,
    ) {
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->payrollRuns->paginate($filters, $perPage);
    }

    public function find(int $id): ?PayrollRun
    {
        return $this->payrollRuns->find($id);
    }

    /**
     * Drafts don't touch the ledger — only approve() does.
     */
    public function create(CreatePayrollRunData $data): PayrollRun
    {
        return $this->payrollRuns->create(
            attributes: [
                'tenant_id' => auth()->user()->tenant_id,
                'period_start' => $data->periodStart,
                'period_end' => $data->periodEnd,
                'pay_date' => $data->payDate,
                'expense_account_id' => $data->expenseAccountId,
                'payable_account_id' => $data->payableAccountId,
                'deductions_payable_account_id' => $data->deductionsPayableAccountId,
                'status' => 'draft',
            ],
            payslips: array_map(fn (PayslipData $payslip) => [
                'employee_id' => $payslip->employeeId,
                'gross_pay' => $payslip->grossPay,
                'deductions' => $payslip->deductions,
                'net_pay' => $payslip->netPay(),
            ], $data->payslips),
        );
    }

    /**
     * Recognizes payroll expense: debits total gross pay, credits total
     * net pay to Salaries Payable and — if any payslip has a deduction —
     * the withheld amount to a Deductions Payable control account.
     */
    public function approve(PayrollRun $payrollRun): PayrollRun
    {
        if (! $payrollRun->status->isEditable()) {
            throw new RuntimeException('This payroll run has already been approved.');
        }

        $totalGross = $payrollRun->totalGross();
        $totalNet = $payrollRun->totalNet();
        $totalDeductions = $payrollRun->totalDeductions();

        if ($totalDeductions > 0 && ! $payrollRun->deductions_payable_account_id) {
            throw new RuntimeException('This payroll run has deductions but no deductions-payable account was set.');
        }

        return DB::transaction(function () use ($payrollRun, $totalGross, $totalNet, $totalDeductions) {
            $lines = [
                new JournalLineData(accountId: $payrollRun->expense_account_id, debit: $totalGross, credit: 0),
                new JournalLineData(accountId: $payrollRun->payable_account_id, debit: 0, credit: $totalNet),
            ];

            if ($totalDeductions > 0) {
                $lines[] = new JournalLineData(accountId: $payrollRun->deductions_payable_account_id, debit: 0, credit: $totalDeductions);
            }

            $this->journals->postJournalEntry(new CreateJournalEntryData(
                date: $payrollRun->pay_date->toDateString(),
                description: "Payroll run {$payrollRun->period_start->toDateString()} to {$payrollRun->period_end->toDateString()}",
                reference: null,
                sourceType: JournalSourceType::Payroll,
                sourceId: $payrollRun->id,
                createdBy: auth()->id(),
                lines: $lines,
            ));

            return $this->payrollRuns->updateStatus($payrollRun, 'approved');
        });
    }

    public function markPaid(PayrollRun $payrollRun, int $paymentAccountId): PayrollRun
    {
        if ($payrollRun->status->value !== 'approved') {
            throw new RuntimeException('This payroll run is not awaiting payment.');
        }

        return DB::transaction(function () use ($payrollRun, $paymentAccountId) {
            $this->journals->postJournalEntry(new CreateJournalEntryData(
                date: now()->toDateString(),
                description: "Payroll payment for {$payrollRun->period_start->toDateString()} to {$payrollRun->period_end->toDateString()}",
                reference: null,
                sourceType: JournalSourceType::Payroll,
                sourceId: $payrollRun->id,
                createdBy: auth()->id(),
                lines: [
                    new JournalLineData(accountId: $payrollRun->payable_account_id, debit: $payrollRun->totalNet(), credit: 0),
                    new JournalLineData(accountId: $paymentAccountId, debit: 0, credit: $payrollRun->totalNet()),
                ],
            ));

            return $this->payrollRuns->updateStatus($payrollRun, 'paid', now());
        });
    }
}
