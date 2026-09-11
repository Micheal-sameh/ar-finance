<?php

namespace App\Repositories\Eloquent;

use App\Models\FixedAsset;
use App\Repositories\Contracts\FixedAssetRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentFixedAssetRepository implements FixedAssetRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return FixedAsset::query()
            ->with(['assetAccount', 'depreciationAccount', 'accumulatedDepreciationAccount'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(): Collection
    {
        return FixedAsset::query()->orderBy('name')->get();
    }

    public function find(int $id): ?FixedAsset
    {
        return FixedAsset::query()
            ->with(['assetAccount', 'depreciationAccount', 'accumulatedDepreciationAccount'])
            ->find($id);
    }

    public function create(array $attributes): FixedAsset
    {
        return FixedAsset::create($attributes);
    }

    public function update(FixedAsset $fixedAsset, array $attributes): FixedAsset
    {
        $fixedAsset->update($attributes);

        return $fixedAsset;
    }

    public function delete(FixedAsset $fixedAsset): bool
    {
        return $fixedAsset->delete();
    }

    public function incrementAccumulatedDepreciation(FixedAsset $fixedAsset, float $amount): FixedAsset
    {
        $fixedAsset->increment('accumulated_depreciation', $amount);

        return $fixedAsset;
    }
}
