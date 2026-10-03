<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\PlatformReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(
        private readonly PlatformReportService $reports,
    ) {}

    public function trialBalance(Request $request): Response
    {
        $from = $request->string('from')->value() ?: now()->startOfYear()->toDateString();
        $to = $request->string('to')->value() ?: now()->toDateString();

        return Inertia::render('Platform/Reports/TrialBalance', [
            'rows' => $this->reports->trialBalance($from, $to),
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function profitAndLoss(Request $request): Response
    {
        $from = $request->string('from')->value() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->value() ?: now()->toDateString();

        return Inertia::render('Platform/Reports/ProfitAndLoss', [
            'rows' => $this->reports->profitAndLoss($from, $to),
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function balanceSheet(Request $request): Response
    {
        $asOf = $request->string('as_of')->value() ?: now()->toDateString();

        return Inertia::render('Platform/Reports/BalanceSheet', [
            'rows' => $this->reports->balanceSheet($asOf),
            'filters' => ['as_of' => $asOf],
        ]);
    }

    public function cashFlow(Request $request): Response
    {
        $from = $request->string('from')->value() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->value() ?: now()->toDateString();

        return Inertia::render('Platform/Reports/CashFlow', [
            'rows' => $this->reports->cashFlow($from, $to),
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function vatReturn(Request $request): Response
    {
        $from = $request->string('from')->value() ?: now()->startOfQuarter()->toDateString();
        $to = $request->string('to')->value() ?: now()->toDateString();

        return Inertia::render('Platform/Reports/VatReturn', [
            'rows' => $this->reports->vatReturn($from, $to),
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function aging(Request $request): Response
    {
        $asOf = $request->string('as_of')->value() ?: now()->toDateString();

        return Inertia::render('Platform/Reports/Aging', [
            'rows' => $this->reports->aging($asOf),
            'filters' => ['as_of' => $asOf],
        ]);
    }
}
