<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsExcel;
use App\Http\Requests\PayrollRuns\MarkPayrollPaidRequest;
use App\Http\Requests\PayrollRuns\StorePayrollRunRequest;
use App\Models\PayrollRun;
use App\Services\EmployeeService;
use App\Services\PayrollRunService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollRunController extends Controller
{
    use ExportsExcel;

    public function __construct(
        private readonly PayrollRunService $payrollRuns,
        private readonly EmployeeService $employees,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PayrollRun::class);

        return Inertia::render('Payroll/PayrollRuns/Index', [
            'payrollRuns' => $this->payrollRuns->paginate($request->only(['status'])),
            'filters' => $request->only(['status']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', PayrollRun::class);

        $payrollRuns = $this->payrollRuns->paginate($request->only(['status']), $this->exportMaxRows());

        $rows = collect($payrollRuns->items())->map(fn (PayrollRun $run) => [
            $run->period_start->toDateString().' – '.$run->period_end->toDateString(),
            $run->pay_date->toDateString(),
            $run->payslips->count(),
            $run->status->value,
            (float) $run->payslips->sum('net_pay'),
        ]);

        return $this->exportXlsx('payroll-runs.xlsx', ['Period', 'Pay Date', 'Employees', 'Status', 'Net Pay'], $rows);
    }

    public function create(): Response
    {
        $this->authorize('create', PayrollRun::class);

        return Inertia::render('Payroll/PayrollRuns/Create', [
            'employees' => $this->employees->all(),
        ]);
    }

    public function show(PayrollRun $payrollRun): Response
    {
        $this->authorize('view', $payrollRun);

        return Inertia::render('Payroll/PayrollRuns/Show', [
            'payrollRun' => $this->payrollRuns->find($payrollRun->id),
        ]);
    }

    public function store(StorePayrollRunRequest $request): RedirectResponse
    {
        $payrollRun = $this->payrollRuns->create($request->toDto());

        return redirect()->route('payroll-runs.show', $payrollRun)->with('success', 'Payroll run saved as draft.');
    }

    public function approve(PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorize('manage', $payrollRun);

        try {
            $this->payrollRuns->approve($payrollRun);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payroll approved and posted to the ledger.');
    }

    public function markPaid(MarkPayrollPaidRequest $request, PayrollRun $payrollRun): RedirectResponse
    {
        try {
            $this->payrollRuns->markPaid($payrollRun, (int) $request->validated('payment_account_id'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payroll marked as paid.');
    }
}
