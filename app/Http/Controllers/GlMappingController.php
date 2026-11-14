<?php

namespace App\Http\Controllers;

use App\Http\Requests\GlMapping\SaveGlMappingRequest;
use App\Http\Requests\GlMapping\SavePaymentAccountsRequest;
use App\Http\Requests\GlMapping\ShowGlMappingRequest;
use App\Services\GlMappingService;
use App\Services\PaymentAccountService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class GlMappingController extends Controller
{
    public function __construct(
        private readonly GlMappingService $glMapping,
        private readonly PaymentAccountService $paymentAccounts,
    ) {}

    public function index(ShowGlMappingRequest $request): Response
    {
        return Inertia::render('Accounting/GlMapping/Index', [
            'rows' => $this->glMapping->rows(),
            'paymentAccounts' => $this->paymentAccounts->configured(),
            'canManage' => $request->user()->can('accounts.manage'),
        ]);
    }

    public function updatePaymentAccounts(SavePaymentAccountsRequest $request): RedirectResponse
    {
        try {
            $this->paymentAccounts->save($request->accounts());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment accounts saved.');
    }

    public function update(SaveGlMappingRequest $request): RedirectResponse
    {
        try {
            $this->glMapping->save($request->mappings());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'GL mapping saved.');
    }
}
