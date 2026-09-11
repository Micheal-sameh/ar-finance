<?php

namespace App\Repositories\Contracts;

use App\Models\PayrollRun;
use Illuminate\Pagination\LengthAwarePaginator;

interface PayrollRunRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    public function find(int $id): ?PayrollRun;

    /**
     * @param  array<int, array<string, mixed>>  $payslips
     */
    public function create(array $attributes, array $payslips): PayrollRun;

    public function updateStatus(PayrollRun $payrollRun, string $status, ?\DateTimeInterface $paidAt = null): PayrollRun;
}
