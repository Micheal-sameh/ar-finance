<?php

namespace App\DTOs;

final readonly class PayslipData
{
    public function __construct(
        public int $employeeId,
        public float $grossPay,
        public float $deductions,
    ) {
    }

    public function netPay(): float
    {
        return round($this->grossPay - $this->deductions, 2);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            employeeId: (int) $data['employee_id'],
            grossPay: (float) $data['gross_pay'],
            deductions: (float) ($data['deductions'] ?? 0),
        );
    }
}
