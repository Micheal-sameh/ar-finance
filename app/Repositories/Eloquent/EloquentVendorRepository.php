<?php

namespace App\Repositories\Eloquent;

use App\Models\Vendor;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentVendorRepository implements VendorRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Vendor::query()
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(): Collection
    {
        return Vendor::query()->orderBy('name')->get();
    }

    public function find(int $id): ?Vendor
    {
        return Vendor::find($id);
    }

    public function create(array $attributes): Vendor
    {
        return Vendor::create($attributes);
    }

    public function update(Vendor $vendor, array $attributes): Vendor
    {
        $vendor->update($attributes);

        return $vendor;
    }

    public function delete(Vendor $vendor): bool
    {
        return $vendor->delete();
    }

    public function hasExpenses(Vendor $vendor): bool
    {
        return $vendor->expenses()->exists();
    }
}
