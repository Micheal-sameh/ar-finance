<?php

namespace App\Http\Controllers;

use App\Exceptions\CostCenterInUseException;
use App\Http\Controllers\Concerns\ExportsExcel;
use App\Http\Requests\CostCenters\SaveCostCenterRequest;
use App\Http\Resources\CostCenterOptionResource;
use App\Models\CostCenter;
use App\Services\CostCenterService;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CostCenterController extends Controller
{
    use ExportsExcel;

    public function __construct(
        private readonly CostCenterService $costCenters,
        private readonly ReportService $reports,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CostCenter::class);

        $from = $request->string('from')->value() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->value() ?: now()->toDateString();

        return Inertia::render('Accounting/CostCenters/Index', [
            'costCenters' => $this->costCenters->paginate($request->only(['type', 'search'])),
            'summary' => $this->reports->costCenterSummary($from, $to),
            'filters' => array_merge($request->only(['type', 'search']), ['from' => $from, 'to' => $to]),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', CostCenter::class);

        $costCenters = $this->costCenters->paginate($request->only(['type', 'search']), $this->exportMaxRows());

        $rows = collect($costCenters->items())->map(fn (CostCenter $costCenter) => [
            $costCenter->name,
            $costCenter->type->value,
            $costCenter->parent?->name,
            (float) $costCenter->budget,
            $costCenter->is_active ? 'Active' : 'Inactive',
        ]);

        return $this->exportXlsx('cost-centers.xlsx', ['Name', 'Type', 'Parent', 'Budget', 'Status'], $rows);
    }

    public function options(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CostCenter::class);

        return CostCenterOptionResource::collection($this->costCenters->all());
    }

    public function store(SaveCostCenterRequest $request): RedirectResponse
    {
        $this->costCenters->create($request->toDto());

        return redirect()->route('cost-centers.index')->with('success', 'Cost center created.');
    }

    public function update(SaveCostCenterRequest $request, CostCenter $costCenter): RedirectResponse
    {
        $this->authorize('update', $costCenter);

        $this->costCenters->update($costCenter, $request->toDto());

        return redirect()->route('cost-centers.index')->with('success', 'Cost center updated.');
    }

    public function destroy(CostCenter $costCenter): RedirectResponse
    {
        $this->authorize('delete', $costCenter);

        try {
            $this->costCenters->delete($costCenter);
        } catch (CostCenterInUseException $e) {
            return redirect()->route('cost-centers.index')->with('error', $e->getMessage());
        }

        return redirect()->route('cost-centers.index')->with('success', 'Cost center deleted.');
    }
}
