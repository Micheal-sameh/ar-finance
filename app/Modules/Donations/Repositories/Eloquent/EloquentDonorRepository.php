<?php

namespace App\Modules\Donations\Repositories\Eloquent;

use App\Modules\Donations\Models\Donor;
use App\Modules\Donations\Repositories\Contracts\DonorRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentDonorRepository implements DonorRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Donor::query()
            ->withCount('donations')
            ->with('tenant:id,name')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(): Collection
    {
        return Donor::query()->orderBy('name')->get(['id', 'name', 'phone']);
    }

    public function create(array $attributes): Donor
    {
        return Donor::create($attributes);
    }

    public function update(Donor $donor, array $attributes): Donor
    {
        $donor->update($attributes);

        return $donor;
    }
}
