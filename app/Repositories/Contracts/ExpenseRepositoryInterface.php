<?php

namespace App\Repositories\Contracts;

use App\Models\Expense;
use Illuminate\Pagination\LengthAwarePaginator;

interface ExpenseRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    public function find(int $id): ?Expense;

    public function create(array $attributes): Expense;

    public function updateStatus(Expense $expense, string $status, ?\DateTimeInterface $paidAt = null): Expense;
}
