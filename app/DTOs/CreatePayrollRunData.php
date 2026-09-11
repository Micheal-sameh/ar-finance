<?php

namespace App\DTOs;

final readonly class CreatePayrollRunData
{
    /**
     * @param  PayslipData[]  $payslips
     */
    public function __construct(
        public string $periodStart,
        public string $periodEnd,
        public string $payDate,
        public int $expenseAccountId,
        public int $payableAccountId,
        public ?int $deductionsPayableAccountId,
        public array $payslips,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            periodStart: $data['period_start'],
            periodEnd: $data['period_end'],
            payDate: $data['pay_date'],
            expenseAccountId: (int) $data['expense_account_id'],
            payableAccountId: (int) $data['payable_account_id'],
            deductionsPayableAccountId: isset($data['deductions_payable_account_id']) ? (int) $data['deductions_payable_account_id'] : null,
            payslips: array_map(
                fn (array $payslip) => PayslipData::fromArray($payslip),
                $data['payslips'],
            ),
        );
    }
}
