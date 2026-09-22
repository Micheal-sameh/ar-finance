<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsExcel;
use App\Http\Requests\Invoices\RecordInvoicePaymentRequest;
use App\Http\Requests\Invoices\StoreInvoiceRequest;
use App\Models\Invoice;
use App\Services\ClientService;
use App\Services\ExchangeRateService;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    use ExportsExcel;

    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly ClientService $clients,
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);

        return Inertia::render('Sales/Invoices/Index', [
            'invoices' => $this->invoices->paginate($request->only(['status', 'search'])),
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = $this->invoices->paginate($request->only(['status', 'search']), $this->exportMaxRows());

        $rows = collect($invoices->items())->map(fn (Invoice $invoice) => [
            $invoice->invoice_number,
            $invoice->client?->name,
            $invoice->issue_date->toDateString(),
            $invoice->due_date->toDateString(),
            $invoice->status->value,
            (float) $invoice->lines->sum(fn ($line) => $line->quantity * $line->unit_price * (1 + $line->tax_rate / 100)),
            $invoice->currency,
        ]);

        return $this->exportXlsx('invoices.xlsx', ['Number', 'Client', 'Issue Date', 'Due Date', 'Status', 'Total', 'Currency'], $rows);
    }

    public function create(): Response
    {
        $this->authorize('create', Invoice::class);

        return Inertia::render('Sales/Invoices/Create', [
            'clients' => $this->clients->all(),
            'baseCurrency' => $this->exchangeRates->baseCurrency(),
            'currencyOptions' => $this->exchangeRates->currencyOptions(),
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
        $settlementRate = $request->validated('settlement_exchange_rate');
        $fxAccountId = $request->validated('fx_gain_loss_account_id');

        try {
            $this->invoices->recordPayment(
                $invoice,
                (int) $request->validated('payment_account_id'),
                $settlementRate !== null ? (float) $settlementRate : null,
                $fxAccountId !== null ? (int) $fxAccountId : null,
            );
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
