<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsExcel;
use App\Http\Requests\Bills\MarkBillPaidRequest;
use App\Http\Requests\Bills\StoreBillRequest;
use App\Models\Bill;
use App\Services\BillService;
use App\Services\VendorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillController extends Controller
{
    use ExportsExcel;

    public function __construct(
        private readonly BillService $bills,
        private readonly VendorService $vendors,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Bill::class);

        return Inertia::render('Purchases/Bills/Index', [
            'bills' => $this->bills->paginate($request->only(['status', 'from', 'to', 'search'])),
            'filters' => $request->only(['status', 'from', 'to', 'search']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Bill::class);

        $bills = $this->bills->paginate($request->only(['status', 'from', 'to', 'search']), $this->exportMaxRows());

        $rows = collect($bills->items())->map(fn (Bill $bill) => [
            $bill->bill_number,
            $bill->vendor?->name,
            $bill->due_date->toDateString(),
            $bill->status->value,
            (float) $bill->lines->sum(fn ($line) => $line->quantity * $line->unit_price),
        ]);

        return $this->exportXlsx('bills.xlsx', ['Bill Number', 'Vendor', 'Due Date', 'Status', 'Total'], $rows);
    }

    public function create(): Response
    {
        $this->authorize('create', Bill::class);

        return Inertia::render('Purchases/Bills/Create', [
            'vendors' => $this->vendors->all(),
        ]);
    }

    public function show(Bill $bill): Response
    {
        $this->authorize('view', $bill);

        return Inertia::render('Purchases/Bills/Show', [
            'bill' => $this->bills->find($bill->id),
        ]);
    }

    public function store(StoreBillRequest $request): RedirectResponse
    {
        $bill = $this->bills->create($request->toDto());

        return redirect()->route('bills.show', $bill)->with('success', 'Bill saved as draft.');
    }

    public function approve(Bill $bill): RedirectResponse
    {
        $this->authorize('manage', $bill);

        try {
            $this->bills->approve($bill);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Bill approved and posted to the ledger.');
    }

    public function markPaid(MarkBillPaidRequest $request, Bill $bill): RedirectResponse
    {
        try {
            $this->bills->markPaid($bill, (int) $request->validated('payment_account_id'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Bill marked as paid.');
    }
}
