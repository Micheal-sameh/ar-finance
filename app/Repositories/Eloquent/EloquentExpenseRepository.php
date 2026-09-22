<?php

namespace App\Repositories\Eloquent;

use App\Models\Expense;
use App\Repositories\Contracts\ExpenseRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentExpenseRepository implements ExpenseRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Expense::query()
            ->with(['account', 'vendor', 'costCenter'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('date', '<=', $to))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('description', 'like', "%{$search}%"))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): ?Expense
    {
        return Expense::query()->with(['account', 'vendor', 'costCenter', 'payableAccount'])->find($id);
    }

    public function create(array $attributes): Expense
    {
        return Expense::create($attributes);
    }

    public function updateStatus(Expense $expense, string $status, ?\DateTimeInterface $paidAt = null): Expense
    {
        $expense->update(['status' => $status, 'paid_at' => $paidAt]);

        return $expense;
    }
}
