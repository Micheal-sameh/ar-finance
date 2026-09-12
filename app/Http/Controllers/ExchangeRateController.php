<?php

namespace App\Http\Controllers;

use App\Exceptions\ExchangeRateProviderException;
use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeRateController extends Controller
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRates,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ExchangeRate::class);

        $date = $request->string('date')->value() ?: now()->toDateString();

        return Inertia::render('Tools/ExchangeRates/Index', [
            'report' => $this->exchangeRates->ratesForDate($date),
            'canManage' => $request->user()->can('manage', ExchangeRate::class),
        ]);
    }

    public function sync(Request $request): RedirectResponse
    {
        $this->authorize('manage', ExchangeRate::class);

        $date = $request->string('date')->value() ?: now()->toDateString();

        try {
            $this->exchangeRates->sync($date);

            return back()->with('success', "Exchange rates refreshed for {$date}.");
        } catch (ExchangeRateProviderException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
