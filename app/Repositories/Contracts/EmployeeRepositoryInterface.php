<?php

namespace App\Repositories\Contracts;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface EmployeeRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    /**
     * All active employees, for payroll-run selection.
     */
    public function all(): Collection;

    public function find(int $id): ?Employee;

    public function create(array $attributes): Employee;

    public function update(Employee $employee, array $attributes): Employee;

    public function delete(Employee $employee): bool;

    public function hasPayslips(Employee $employee): bool;
}
