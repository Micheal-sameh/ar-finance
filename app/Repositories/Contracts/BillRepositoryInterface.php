<?php

namespace App\Repositories\Contracts;

use App\Models\Bill;
use Illuminate\Pagination\LengthAwarePaginator;

interface BillRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    public function find(int $id): ?Bill;

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function create(array $attributes, array $lines): Bill;

    public function updateStatus(Bill $bill, string $status, ?\DateTimeInterface $paidAt = null): Bill;
}
