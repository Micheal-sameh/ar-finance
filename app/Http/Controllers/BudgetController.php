<?php

namespace App\Http\Controllers;

use App\Exceptions\BudgetImportException;
use App\Http\Controllers\Concerns\ExportsExcel;
use App\Http\Requests\Budgets\ImportBudgetRequest;
use App\Http\Requests\Budgets\SaveBudgetRequest;
use App\Models\Budget;
use App\Services\BudgetService;
use App\Services\CostCenterService;
use App\Support\Excel\BudgetImportTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BudgetController extends Controller
{
    use ExportsExcel;

    public function __construct(
        private readonly BudgetService $budgets,
        private readonly CostCenterService $costCenters,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Budget::class);

        return Inertia::render('Accounting/Budgets/Index', [
            'budgets' => $this->budgets->overview(),
            'currentYear' => now()->year,
        ]);
    }

    public function edit(int $year): Response
    {
        $this->authorize('viewAny', Budget::class);
        abort_unless($year >= 2000 && $year <= 2100, 404);

        return Inertia::render('Accounting/Budgets/Edit', [
            'budget' => $this->budgets->editorData($year),
            'imported' => session('budget_import'),
            'costCenters' => $this->costCenters->all()->map(fn ($center) => ['id' => $center->id, 'name' => $center->name])->values(),
        ]);
    }

    public function importTemplate(): StreamedResponse
    {
        $this->authorize('viewAny', Budget::class);

        return BudgetImportTemplate::download($this->budgets->templateAccounts(), $this->budgets->costCenterNames());
    }

    public function export(int $year): StreamedResponse
    {
        $this->authorize('viewAny', Budget::class);
        abort_unless($year >= 2000 && $year <= 2100, 404);

        return BudgetImportTemplate::export("budget-{$year}.xlsx", $this->budgets->exportRows($year), $this->budgets->costCenterNames());
    }

    /**
     * Reads the spreadsheet and sends the lines back to the budget page for
     * review — nothing is saved until the user clicks Save budget.
     */
    public function import(ImportBudgetRequest $request, int $year): RedirectResponse
    {
        abort_unless($year >= 2000 && $year <= 2100, 404);

        try {
            $lines = $this->budgets->resolveImport($request->rows());
        } catch (BudgetImportException $e) {
            return back()->withErrors(['file' => implode("\n", $e->rowErrors())]);
        }

        return redirect()->route('budgets.edit', $year)
            ->with('budget_import', $lines)
            ->with('success', count($lines).' budget line(s) read from the file. Review them, then click Save budget.');
    }

    public function update(SaveBudgetRequest $request, int $year): RedirectResponse
    {
        abort_unless($year >= 2000 && $year <= 2100, 404);

        $this->budgets->save($year, $request->validated('lines'));

        return back()->with('success', "Budget for {$year} saved.");
    }

    public function destroy(int $year): RedirectResponse
    {
        $this->authorize('manage', Budget::class);

        $this->budgets->delete($year);

        return redirect()->route('budgets.index')->with('success', "Budget for {$year} deleted.");
    }

    public function report(Request $request): Response
    {
        $this->authorize('viewAny', Budget::class);

        [$from, $to, $costCenter] = $this->reportFilters($request);

        return Inertia::render('Accounting/Reports/BudgetVsActual', [
            'report' => $this->budgets->budgetVsActual($from, $to, $costCenter),
            'filters' => ['from' => $from, 'to' => $to, 'cost_center_id' => $request->string('cost_center_id')->value() ?: null],
            'costCenters' => $this->costCenters->all()->map(fn ($center) => ['id' => $center->id, 'name' => $center->name])->values(),
        ]);
    }

    public function reportExport(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Budget::class);

        [$from, $to, $costCenter] = $this->reportFilters($request);
        $report = $this->budgets->budgetVsActual($from, $to, $costCenter);

        $rows = collect();

        foreach (['Revenue' => ['revenue', 'total_revenue'], 'Expense' => ['expenses', 'total_expenses']] as $section => [$rowsKey, $totalKey]) {
            foreach ($report[$rowsKey] as $row) {
                $rows->push([$section, $row['code'], $row['name'], $row['budget'], $row['actual'], $row['variance'], $row['percent_used']]);
            }
            $total = $report[$totalKey];
            $rows->push([$section, '', "Total {$section}", $total['budget'], $total['actual'], $total['variance'], $total['percent_used']]);
        }

        $net = $report['net_profit'];
        $rows->push(['', '', 'Net Profit', $net['budget'], $net['actual'], $net['variance'], null]);

        return $this->exportXlsx('budget-vs-actual.xlsx', ['Section', 'Code', 'Account', 'Budget', 'Actual', 'Variance', '% Used'], $rows);
    }

    /**
     * @return array{0: string, 1: string, 2: int|string|null}
     */
    private function reportFilters(Request $request): array
    {
        $from = $request->string('from')->value() ?: now()->startOfYear()->toDateString();
        $to = $request->string('to')->value() ?: now()->toDateString();
        $center = $request->string('cost_center_id')->value();

        return [$from, $to, $center === '' ? null : ($center === 'unassigned' ? 'unassigned' : (int) $center)];
    }
}
