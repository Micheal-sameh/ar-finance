<?php

namespace App\Modules\Donations\Repositories\Contracts;

use App\Modules\Donations\Models\Donor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface DonorRepositoryInterface
{
    /**
     * @param  array{search?: string|null}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    /**
     * @return Collection<int, Donor>
     */
    public function all(): Collection;

    public function create(array $attributes): Donor;

    public function update(Donor $donor, array $attributes): Donor;
}
