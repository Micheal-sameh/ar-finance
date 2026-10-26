<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceivedPayments\StoreReceivedPaymentRequest;
use App\Models\ReceivedPayment;
use App\Services\ClientService;
use App\Services\ReceivedPaymentService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ReceivedPaymentController extends Controller
{
    public function __construct(
        private readonly ReceivedPaymentService $receivedPayments,
        private readonly ClientService $clients,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ReceivedPayment::class);

        $viewingAllTenants = $this->tenantContext->isViewingAllTenants();
        $filters = $request->only(['from', 'to', 'search']);

        return Inertia::render('Sales/ReceivedPayments/Index', [
            'receivedPayments' => $this->receivedPayments->paginate($filters)
                ->through(fn (ReceivedPayment $payment) => [
                    ...$payment->toArray(),
                    'tenant_name' => $viewingAllTenants ? $payment->tenant?->name : null,
                ]),
            'filters' => $filters,
            'viewingAllTenants' => $viewingAllTenants,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ReceivedPayment::class);

        return Inertia::render('Sales/ReceivedPayments/Create', [
            'clients' => $this->clients->all(),
        ]);
    }

    public function store(StoreReceivedPaymentRequest $request): RedirectResponse
    {
        try {
            $this->receivedPayments->record($request->validated());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('received-payments.index')->with('success', 'Payment received and posted to the ledger.');
    }
}
