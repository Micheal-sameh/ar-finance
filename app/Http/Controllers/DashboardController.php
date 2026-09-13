<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\ExchangeRateService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly ExchangeRateService $exchangeRates,
    ) {
    }

    public function __invoke(): Response
    {
        return Inertia::render('Dashboard/Index', [
            'summary' => $this->dashboard->summary(),
            'baseCurrency' => $this->exchangeRates->baseCurrency(),
        ]);
    }
}
