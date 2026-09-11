<?php

namespace App\Repositories\Eloquent;

use App\Models\Employee;
use App\Repositories\Contracts\EmployeeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentEmployeeRepository implements EmployeeRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Employee::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(): Collection
    {
        return Employee::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function find(int $id): ?Employee
    {
        return Employee::find($id);
    }

    public function create(array $attributes): Employee
    {
        return Employee::create($attributes);
    }

    public function update(Employee $employee, array $attributes): Employee
    {
        $employee->update($attributes);

        return $employee;
    }

    public function delete(Employee $employee): bool
    {
        return $employee->delete();
    }

    public function hasPayslips(Employee $employee): bool
    {
        return $employee->payslips()->exists();
    }
}
