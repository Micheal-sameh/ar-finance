<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseOrders\ConvertToBillRequest;
use App\Http\Requests\PurchaseOrders\StorePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use App\Services\VendorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrders,
        private readonly VendorService $vendors,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        return Inertia::render('Purchases/PurchaseOrders/Index', [
            'purchaseOrders' => $this->purchaseOrders->paginate($request->only(['status', 'search'])),
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', PurchaseOrder::class);

        return Inertia::render('Purchases/PurchaseOrders/Create', [
            'vendors' => $this->vendors->all(),
        ]);
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $this->authorize('view', $purchaseOrder);

        return Inertia::render('Purchases/PurchaseOrders/Show', [
            'purchaseOrder' => $this->purchaseOrders->find($purchaseOrder->id),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $purchaseOrder = $this->purchaseOrders->create($request->toDto());

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order saved as draft.');
    }

    public function send(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('manage', $purchaseOrder);

        try {
            $this->purchaseOrders->send($purchaseOrder);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Purchase order sent.');
    }

    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('manage', $purchaseOrder);

        try {
            $this->purchaseOrders->cancel($purchaseOrder);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Purchase order cancelled.');
    }

    public function convertToBill(ConvertToBillRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            $bill = $this->purchaseOrders->convertToBill(
                $purchaseOrder,
                $request->string('bill_number')->value(),
                $request->string('bill_date')->value(),
                $request->string('due_date')->value(),
                $request->integer('payable_account_id'),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('bills.show', $bill)->with('success', 'Bill created from purchase order — still needs approval to post.');
    }
}
