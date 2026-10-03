<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\ExchangeRateService;
use App\Services\PlatformDashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly PlatformDashboardService $dashboard,
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('Platform/Dashboard', [
            'summary' => $this->dashboard->summary(),
            'baseCurrency' => $this->exchangeRates->baseCurrency(),
        ]);
    }
}
