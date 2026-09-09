<?php

namespace App\Repositories\Contracts;

use App\Models\CostCenter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface CostCenterRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    /**
     * All active cost centers, for pickers and budget-vs-actual reports.
     */
    public function all(): Collection;

    public function find(int $id): ?CostCenter;

    public function create(array $attributes): CostCenter;

    public function update(CostCenter $costCenter, array $attributes): CostCenter;

    public function delete(CostCenter $costCenter): bool;

    public function isInUse(CostCenter $costCenter): bool;

    public function hasChildren(CostCenter $costCenter): bool;
}
