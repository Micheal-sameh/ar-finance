<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsExcel;
use App\Http\Controllers\Concerns\GeneratesPdf;
use App\Http\Requests\Bills\ApproveBillRequest;
use App\Http\Requests\Bills\ListBillsRequest;
use App\Http\Requests\Bills\MarkBillPaidRequest;
use App\Http\Requests\Bills\PayBillsRequest;
use App\Http\Requests\Bills\StoreBillRequest;
use App\Models\Bill;
use App\Services\BillService;
use App\Services\VendorService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillController extends Controller
{
    use ExportsExcel;
    use GeneratesPdf;

    public function __construct(
        private readonly BillService $bills,
        private readonly VendorService $vendors,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(ListBillsRequest $request): Response
    {
        $viewingAllTenants = $this->tenantContext->isViewingAllTenants();

        return Inertia::render('Purchases/Bills/Index', [
            'bills' => $this->bills->listing($request->filters(), $viewingAllTenants),
            'filters' => $request->filters(),
            'viewingAllTenants' => $viewingAllTenants,
        ]);
    }

    public function export(ListBillsRequest $request): StreamedResponse
    {
        return $this->exportXlsx(
            'bills.xlsx',
            ['Bill Number', 'Vendor', 'Due Date', 'Status', 'Total', 'Paid', 'Balance Due'],
            $this->bills->exportRows($request->filters(), $this->exportMaxRows()),
        );
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

    public function pdf(Bill $bill): StreamedResponse
    {
        $this->authorize('view', $bill);

        return $this->downloadPdf(
            "{$bill->bill_number}.pdf",
            'pdf.bill',
            ['bill' => $this->bills->findForPdf($bill->id)],
        );
    }

    public function store(StoreBillRequest $request): RedirectResponse
    {
        $bill = $this->bills->create($request->toDto());

        return redirect()->route('bills.show', $bill)->with('success', 'Bill saved as draft.');
    }

    public function approve(ApproveBillRequest $request, Bill $bill): RedirectResponse
    {
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
            $this->bills->recordPayment($bill, $request->paymentAccountId(), $request->amount(), $request->paidOn(), $request->reference());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment recorded.');
    }

    public function payMany(PayBillsRequest $request): RedirectResponse
    {
        try {
            $paid = $this->bills->payMany($request->billIds(), $request->paymentAccountId(), $request->paidOn(), $request->reference());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Paid {$paid} bill(s).");
    }
}
