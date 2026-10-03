<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsExcel;
use App\Http\Requests\FixedAssets\RunDepreciationRequest;
use App\Http\Requests\FixedAssets\StoreFixedAssetRequest;
use App\Models\Account;
use App\Models\FixedAsset;
use App\Services\FixedAssetService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FixedAssetController extends Controller
{
    use ExportsExcel;

    public function __construct(
        private readonly FixedAssetService $fixedAssets,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FixedAsset::class);

        $viewingAllTenants = $this->tenantContext->isViewingAllTenants();

        $fixedAssets = $this->fixedAssets->paginate($request->only(['search']))
            ->through(fn (FixedAsset $asset) => [
                ...$asset->toArray(),
                'tenant_name' => $viewingAllTenants ? $asset->tenant?->name : null,
            ]);

        return Inertia::render('Accounting/FixedAssets/Index', [
            'fixedAssets' => $fixedAssets,
            'filters' => $request->only(['search']),
            'viewingAllTenants' => $viewingAllTenants,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', FixedAsset::class);

        $fixedAssets = $this->fixedAssets->paginate($request->only(['search']), $this->exportMaxRows());

        $rows = collect($fixedAssets->items())->map(fn (FixedAsset $asset) => [
            $asset->name,
            $asset->purchase_date->toDateString(),
            (float) $asset->cost,
            (float) $asset->accumulated_depreciation,
            (float) $asset->cost - (float) $asset->accumulated_depreciation,
        ]);

        return $this->exportXlsx('fixed-assets.xlsx', ['Name', 'Purchase Date', 'Cost', 'Accumulated Depreciation', 'Net Book Value'], $rows);
    }

    public function create(): Response
    {
        $this->authorize('create', FixedAsset::class);

        return Inertia::render('Accounting/FixedAssets/Create', [
            'defaultDepreciationAccountId' => Account::where('code', '5310')->value('id'),
            'defaultAccumulatedDepreciationAccountId' => Account::where('code', '1120')->value('id'),
        ]);
    }

    public function show(FixedAsset $fixedAsset): Response
    {
        $this->authorize('view', $fixedAsset);

        return Inertia::render('Accounting/FixedAssets/Show', [
            'fixedAsset' => $this->fixedAssets->find($fixedAsset->id),
            'schedule' => $this->fixedAssets->depreciationSchedule($fixedAsset),
        ]);
    }

    public function store(StoreFixedAssetRequest $request): RedirectResponse
    {
        $fixedAsset = $this->fixedAssets->create($request->toDto());

        return redirect()->route('fixed-assets.show', $fixedAsset)->with('success', 'Fixed asset added to the register.');
    }

    public function postDepreciation(FixedAsset $fixedAsset): RedirectResponse
    {
        $this->authorize('manage', $fixedAsset);

        try {
            $this->fixedAssets->postDepreciation($fixedAsset);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Depreciation posted to the ledger.');
    }

    public function runAll(RunDepreciationRequest $request): RedirectResponse
    {
        $results = $this->fixedAssets->runMonthlyDepreciationForAll($request->validated('month'));

        $posted = count(array_filter($results, fn ($r) => $r['posted']));
        $skipped = count($results) - $posted;

        $message = "Posted depreciation for {$posted} asset(s).";
        if ($skipped > 0) {
            $message .= " {$skipped} skipped (already posted or fully depreciated).";
        }

        return redirect()->route('fixed-assets.index')->with('success', $message);
    }
}
