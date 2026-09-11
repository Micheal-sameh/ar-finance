<?php

namespace App\Http\Controllers;

use App\Http\Requests\FixedAssets\RunDepreciationRequest;
use App\Http\Requests\FixedAssets\StoreFixedAssetRequest;
use App\Models\FixedAsset;
use App\Services\FixedAssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class FixedAssetController extends Controller
{
    public function __construct(
        private readonly FixedAssetService $fixedAssets,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FixedAsset::class);

        return Inertia::render('Accounting/FixedAssets/Index', [
            'fixedAssets' => $this->fixedAssets->paginate($request->only(['search'])),
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', FixedAsset::class);

        return Inertia::render('Accounting/FixedAssets/Create');
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
