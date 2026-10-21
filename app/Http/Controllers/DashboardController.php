<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\ExchangeRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        // A role like Booking Agent has no accounting access at all — send
        // it straight to the one thing it can use instead of a financial
        // dashboard full of data (and links) it's not authorized to open.
        if (! $request->user()->can('accounts.view') && $request->user()->can('bookings.view')) {
            return redirect()->route('bookings.index');
        }

        return Inertia::render('Dashboard/Index', [
            'summary' => $this->dashboard->summary(),
            'baseCurrency' => $this->exchangeRates->baseCurrency(),
        ]);
    }
}
