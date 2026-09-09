<?php

namespace App\Repositories\Contracts;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface VendorRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    public function all(): Collection;

    public function find(int $id): ?Vendor;

    public function create(array $attributes): Vendor;

    public function update(Vendor $vendor, array $attributes): Vendor;

    public function delete(Vendor $vendor): bool;

    public function hasExpenses(Vendor $vendor): bool;
}
