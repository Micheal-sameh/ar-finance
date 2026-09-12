<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\CurrencyRevaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyRevaluationController extends Controller
{
    public function __construct(
        private readonly CurrencyRevaluationService $revaluations,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);

        $asOf = $request->string('date')->value() ?: now()->toDateString();

        return Inertia::render('Accounting/Revaluation/Index', [
            'preview' => $this->revaluations->preview($asOf),
            'filters' => ['date' => $asOf],
            'canManage' => $request->user()->can('invoices.manage'),
        ]);
    }

    public function revalue(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Invoice::class);

        if (! $request->user()->can('invoices.manage')) {
            abort(403);
        }

        $data = $request->validate([
            'date' => ['required', 'date'],
            'fx_gain_loss_account_id' => ['required', 'integer', 'exists:accounts,id'],
        ]);

        $result = $this->revaluations->revalueAll($data['date'], (int) $data['fx_gain_loss_account_id']);

        if ($result['posted'] === 0) {
            return back()->with('success', 'Nothing to revalue — every outstanding foreign-currency invoice is already at the current rate.');
        }

        return back()->with(
            'success',
            "Revalued {$result['posted']} invoice(s), net unrealized ".($result['total_unrealized_gain_loss'] >= 0 ? 'gain' : 'loss').' of '.number_format(abs($result['total_unrealized_gain_loss']), 2).'.',
        );
    }
}
