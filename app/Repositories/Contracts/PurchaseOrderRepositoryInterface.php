<?php

namespace App\Repositories\Contracts;

use App\Models\PurchaseOrder;
use Illuminate\Pagination\LengthAwarePaginator;

interface PurchaseOrderRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    public function find(int $id): ?PurchaseOrder;

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function create(array $attributes, array $lines): PurchaseOrder;

    public function updateStatus(PurchaseOrder $purchaseOrder, string $status): PurchaseOrder;
}
