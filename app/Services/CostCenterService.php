<?php

namespace App\Services;

use App\DTOs\CreateCostCenterData;
use App\Enums\CostCenterType;
use App\Exceptions\CostCenterImportException;
use App\Exceptions\CostCenterInUseException;
use App\Models\CostCenter;
use App\Repositories\Contracts\CostCenterRepositoryInterface;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CostCenterService
{
    public function __construct(
        private readonly CostCenterRepositoryInterface $costCenters,
    ) {}

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
            'tenant_id' => app(TenantContext::class)->id(),
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
     * Creates every center in the file, or none: names must be unique (within
     * the file and against existing centers, compared case-insensitively),
     * and a parent must be an existing center or another row in the file —
     * rows are created parents-first regardless of their order.
     *
     * @param  array<int, array{row: int, name: string, type: CostCenterType, parent: ?string, budget: ?float, is_active: bool}>  $rows
     * @return int how many centers were created
     *
     * @throws CostCenterImportException
     */
    public function import(array $rows): int
    {
        $idsByName = [];

        foreach ($this->costCenters->allIncludingInactive() as $existing) {
            $idsByName[mb_strtolower($existing->name)] = $existing->id;
        }

        $errors = [];
        $namesInFile = [];

        foreach ($rows as $row) {
            $key = mb_strtolower($row['name']);

            if (isset($idsByName[$key])) {
                $errors[] = "Row {$row['row']}: a center named \"{$row['name']}\" already exists.";
            } elseif (isset($namesInFile[$key])) {
                $errors[] = "Row {$row['row']}: \"{$row['name']}\" appears more than once in the file.";
            }

            $namesInFile[$key] ??= true;
        }

        foreach ($rows as $row) {
            $parentKey = $row['parent'] !== null ? mb_strtolower($row['parent']) : null;

            if ($parentKey !== null && ! isset($idsByName[$parentKey]) && ! isset($namesInFile[$parentKey])) {
                $errors[] = "Row {$row['row']}: parent \"{$row['parent']}\" was not found.";
            } elseif ($parentKey === mb_strtolower($row['name'])) {
                $errors[] = "Row {$row['row']}: a center cannot be its own parent.";
            }
        }

        if ($errors !== []) {
            throw new CostCenterImportException($errors);
        }

        $pending = $rows;

        return DB::transaction(function () use (&$pending, &$idsByName) {
            $created = 0;

            while ($pending !== []) {
                $progressed = false;

                foreach ($pending as $index => $row) {
                    $parentKey = $row['parent'] !== null ? mb_strtolower($row['parent']) : null;

                    if ($parentKey !== null && ! isset($idsByName[$parentKey])) {
                        continue;
                    }

                    $center = $this->create(new CreateCostCenterData(
                        name: $row['name'],
                        type: $row['type'],
                        budget: $row['budget'],
                        parentId: $parentKey !== null ? $idsByName[$parentKey] : null,
                        isActive: $row['is_active'],
                    ));

                    $idsByName[mb_strtolower($row['name'])] = $center->id;
                    unset($pending[$index]);
                    $created++;
                    $progressed = true;
                }

                if (! $progressed) {
                    throw new CostCenterImportException(
                        array_map(fn (array $row) => "Row {$row['row']}: \"{$row['name']}\" is part of a parent loop.", $pending),
                    );
                }
            }

            return $created;
        });
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
