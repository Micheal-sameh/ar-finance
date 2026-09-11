<?php

namespace App\Http\Controllers;

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

class PayrollRunController extends Controller
{
    public function __construct(
        private readonly PayrollRunService $payrollRuns,
        private readonly EmployeeService $employees,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PayrollRun::class);

        return Inertia::render('Payroll/PayrollRuns/Index', [
            'payrollRuns' => $this->payrollRuns->paginate($request->only(['status'])),
            'filters' => $request->only(['status']),
        ]);
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
