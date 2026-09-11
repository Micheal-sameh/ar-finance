<?php

namespace App\Repositories\Contracts;

use App\Models\FixedAsset;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface FixedAssetRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    public function all(): Collection;

    public function find(int $id): ?FixedAsset;

    public function create(array $attributes): FixedAsset;

    public function update(FixedAsset $fixedAsset, array $attributes): FixedAsset;

    public function delete(FixedAsset $fixedAsset): bool;

    public function incrementAccumulatedDepreciation(FixedAsset $fixedAsset, float $amount): FixedAsset;
}
