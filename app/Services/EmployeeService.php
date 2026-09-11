<?php

namespace App\Services;

use App\DTOs\CreateEmployeeData;
use App\Models\Employee;
use App\Repositories\Contracts\EmployeeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use RuntimeException;

class EmployeeService
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employees,
    ) {
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->employees->paginate($filters, $perPage);
    }

    public function all(): Collection
    {
        return $this->employees->all();
    }

    public function create(CreateEmployeeData $data): Employee
    {
        return $this->employees->create([
            'name' => $data->name,
            'email' => $data->email,
            'job_title' => $data->jobTitle,
            'salary' => $data->salary,
            'hire_date' => $data->hireDate,
            'is_active' => $data->isActive,
        ]);
    }

    public function update(Employee $employee, CreateEmployeeData $data): Employee
    {
        return $this->employees->update($employee, [
            'name' => $data->name,
            'email' => $data->email,
            'job_title' => $data->jobTitle,
            'salary' => $data->salary,
            'hire_date' => $data->hireDate,
            'is_active' => $data->isActive,
        ]);
    }

    public function delete(Employee $employee): void
    {
        if ($this->employees->hasPayslips($employee)) {
            throw new RuntimeException("Employee {$employee->name} cannot be deleted: they have payslips on file.");
        }

        $this->employees->delete($employee);
    }
}
