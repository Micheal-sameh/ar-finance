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

    /**
     * Every cost center, active or not — the import needs all existing names
     * to reject duplicates and to resolve parents.
     */
    public function allIncludingInactive(): Collection;

    public function find(int $id): ?CostCenter;

    public function create(array $attributes): CostCenter;

    public function update(CostCenter $costCenter, array $attributes): CostCenter;

    public function delete(CostCenter $costCenter): bool;

    public function isInUse(CostCenter $costCenter): bool;

    public function hasChildren(CostCenter $costCenter): bool;
}
