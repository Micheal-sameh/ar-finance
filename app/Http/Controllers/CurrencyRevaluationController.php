<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsExcel;
use App\Models\Invoice;
use App\Services\CurrencyRevaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CurrencyRevaluationController extends Controller
{
    use ExportsExcel;

    public function __construct(
        private readonly CurrencyRevaluationService $revaluations,
    ) {}

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

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $asOf = $request->string('date')->value() ?: now()->toDateString();
        $preview = $this->revaluations->preview($asOf);

        $rows = collect($preview['rows'])->map(fn (array $row) => [
            $row['invoice_number'],
            $row['client_name'],
            $row['currency'],
            $row['old_rate'],
            $row['new_rate'],
            $row['old_base_value'],
            $row['new_base_value'],
            $row['unrealized_gain_loss'],
        ]);

        return $this->exportXlsx('currency-revaluation.xlsx', [
            'Invoice', 'Client', 'Currency', 'Old Rate', 'New Rate', 'Old Value', 'New Value', 'Unrealized Gain/Loss',
        ], $rows);
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
