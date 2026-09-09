<?php

namespace App\Services;

use App\DTOs\CreateCostCenterData;
use App\Exceptions\CostCenterInUseException;
use App\Models\CostCenter;
use App\Repositories\Contracts\CostCenterRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CostCenterService
{
    public function __construct(
        private readonly CostCenterRepositoryInterface $costCenters,
    ) {
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->costCenters->paginate($filters, $perPage);
    }

    public function all(): Collection
    {
        return $this->costCenters->all();
    }

    public function create(CreateCostCenterData $data): CostCenter
    {
        return $this->costCenters->create([
            'name' => $data->name,
            'type' => $data->type,
            'budget' => $data->budget,
            'parent_id' => $data->parentId,
            'is_active' => $data->isActive,
        ]);
    }

    public function update(CostCenter $costCenter, CreateCostCenterData $data): CostCenter
    {
        return $this->costCenters->update($costCenter, [
            'name' => $data->name,
            'type' => $data->type,
            'budget' => $data->budget,
            'parent_id' => $data->parentId,
            'is_active' => $data->isActive,
        ]);
    }

    /**
     * @throws CostCenterInUseException
     */
    public function delete(CostCenter $costCenter): void
    {
        if ($this->costCenters->isInUse($costCenter)) {
            throw CostCenterInUseException::hasActivity($costCenter->name);
        }

        if ($this->costCenters->hasChildren($costCenter)) {
            throw CostCenterInUseException::hasChildren($costCenter->name);
        }

        $this->costCenters->delete($costCenter);
    }
}
