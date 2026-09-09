<?php

namespace App\Http\Controllers;

use App\Http\Requests\Expenses\MarkExpensePaidRequest;
use App\Http\Requests\Expenses\StoreExpenseRequest;
use App\Models\Expense;
use App\Services\ExpenseService;
use App\Services\VendorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenses,
        private readonly VendorService $vendors,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Expense::class);

        return Inertia::render('Purchases/Expenses/Index', [
            'expenses' => $this->expenses->paginate($request->only(['status', 'search'])),
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Expense::class);

        return Inertia::render('Purchases/Expenses/Create', [
            'vendors' => $this->vendors->all(),
        ]);
    }

    public function show(Expense $expense): Response
    {
        $this->authorize('view', $expense);

        return Inertia::render('Purchases/Expenses/Show', [
            'expense' => $this->expenses->find($expense->id),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $expense = $this->expenses->create($request->toDto());

        return redirect()->route('expenses.show', $expense)->with('success', 'Expense recorded — pending approval.');
    }

    public function approve(Expense $expense): RedirectResponse
    {
        $this->authorize('manage', $expense);

        try {
            $this->expenses->approve($expense);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Expense approved and posted to the ledger.');
    }

    public function markPaid(MarkExpensePaidRequest $request, Expense $expense): RedirectResponse
    {
        try {
            $this->expenses->markPaid($expense, (int) $request->validated('payment_account_id'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Expense marked as paid.');
    }
}
