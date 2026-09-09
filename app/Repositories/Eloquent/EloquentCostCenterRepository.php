<?php

namespace App\Repositories\Eloquent;

use App\Models\CostCenter;
use App\Repositories\Contracts\CostCenterRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentCostCenterRepository implements CostCenterRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return CostCenter::query()
            ->with('parent')
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(): Collection
    {
        return CostCenter::query()
            ->with('parent')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): ?CostCenter
    {
        return CostCenter::query()->with(['parent', 'children'])->find($id);
    }

    public function create(array $attributes): CostCenter
    {
        return CostCenter::create($attributes);
    }

    public function update(CostCenter $costCenter, array $attributes): CostCenter
    {
        $costCenter->update($attributes);

        return $costCenter;
    }

    public function delete(CostCenter $costCenter): bool
    {
        return $costCenter->delete();
    }

    public function isInUse(CostCenter $costCenter): bool
    {
        return $costCenter->journalLines()->exists() || $costCenter->expenses()->exists();
    }

    public function hasChildren(CostCenter $costCenter): bool
    {
        return $costCenter->children()->exists();
    }
}
