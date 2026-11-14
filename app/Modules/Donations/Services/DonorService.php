<?php

namespace App\Modules\Donations\Services;

use App\Modules\Donations\DTOs\SaveDonorData;
use App\Modules\Donations\Models\Donor;
use App\Modules\Donations\Repositories\Contracts\DonorRepositoryInterface;
use App\Support\TenantContext;
use Illuminate\Pagination\LengthAwarePaginator;

class DonorService
{
    public function __construct(
        private readonly DonorRepositoryInterface $donors,
    ) {}

    /**
     * @param  array{search?: string|null}  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return $this->donors->paginate($filters);
    }

    public function create(SaveDonorData $data): Donor
    {
        return $this->donors->create([
            'tenant_id' => app(TenantContext::class)->id(),
            'name' => $data->name,
            'phone' => $data->phone,
            'email' => $data->email,
            'notes' => $data->notes,
        ]);
    }

    public function update(Donor $donor, SaveDonorData $data): Donor
    {
        return $this->donors->update($donor, [
            'name' => $data->name,
            'phone' => $data->phone,
            'email' => $data->email,
            'notes' => $data->notes,
        ]);
    }
}
