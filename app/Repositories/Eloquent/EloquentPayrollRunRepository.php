<?php

namespace App\Repositories\Eloquent;

use App\Models\PayrollRun;
use App\Repositories\Contracts\PayrollRunRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentPayrollRunRepository implements PayrollRunRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return PayrollRun::query()
            ->with('payslips')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('period_start', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('period_end', '<=', $to))
            ->orderByDesc('period_start')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): ?PayrollRun
    {
        return PayrollRun::query()
            ->with(['payslips.employee', 'expenseAccount', 'payableAccount', 'deductionsPayableAccount'])
            ->find($id);
    }

    public function create(array $attributes, array $payslips): PayrollRun
    {
        $payrollRun = PayrollRun::create($attributes);
        $payrollRun->payslips()->createMany($payslips);

        return $payrollRun->load(['payslips.employee']);
    }

    public function updateStatus(PayrollRun $payrollRun, string $status, ?\DateTimeInterface $paidAt = null): PayrollRun
    {
        $payrollRun->update(['status' => $status, 'paid_at' => $paidAt]);

        return $payrollRun;
    }
}
