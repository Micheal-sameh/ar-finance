<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsExcel;
use App\Http\Requests\Employees\SaveEmployeeRequest;
use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    use ExportsExcel;

    public function __construct(
        private readonly EmployeeService $employees,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        return Inertia::render('Payroll/Employees/Index', [
            'employees' => $this->employees->paginate($request->only(['search'])),
            'filters' => $request->only(['search']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Employee::class);

        $employees = $this->employees->paginate($request->only(['search']), $this->exportMaxRows());

        $rows = collect($employees->items())->map(fn (Employee $employee) => [
            $employee->name,
            $employee->job_title,
            (float) $employee->salary,
            $employee->is_active ? 'Active' : 'Inactive',
        ]);

        return $this->exportXlsx('employees.xlsx', ['Name', 'Job Title', 'Salary', 'Status'], $rows);
    }

    public function store(SaveEmployeeRequest $request): RedirectResponse
    {
        $this->employees->create($request->toDto());

        return redirect()->route('employees.index')->with('success', 'Employee added.');
    }

    public function update(SaveEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $this->employees->update($employee, $request->toDto());

        return redirect()->route('employees.index')->with('success', 'Employee updated.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $this->authorize('delete', $employee);

        try {
            $this->employees->delete($employee);
        } catch (RuntimeException $e) {
            return redirect()->route('employees.index')->with('error', $e->getMessage());
        }

        return redirect()->route('employees.index')->with('success', 'Employee deleted.');
    }
}
