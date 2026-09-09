<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invoices\RecordInvoicePaymentRequest;
use App\Http\Requests\Invoices\StoreInvoiceRequest;
use App\Models\Invoice;
use App\Services\ClientService;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly ClientService $clients,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);

        return Inertia::render('Sales/Invoices/Index', [
            'invoices' => $this->invoices->paginate($request->only(['status', 'search'])),
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Invoice::class);

        return Inertia::render('Sales/Invoices/Create', [
            'clients' => $this->clients->all(),
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        return Inertia::render('Sales/Invoices/Show', [
            'invoice' => $this->invoices->find($invoice->id),
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $invoice = $this->invoices->create($request->toDto());

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice saved as draft.');
    }

    public function send(Invoice $invoice): RedirectResponse
    {
        $this->authorize('manage', $invoice);

        try {
            $this->invoices->send($invoice);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Invoice sent and posted to the ledger.');
    }

    public function recordPayment(RecordInvoicePaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        try {
            $this->invoices->recordPayment($invoice, (int) $request->validated('payment_account_id'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment recorded.');
    }

    public function void(Invoice $invoice): RedirectResponse
    {
        $this->authorize('manage', $invoice);

        try {
            $this->invoices->void($invoice);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Invoice voided.');
    }
}
