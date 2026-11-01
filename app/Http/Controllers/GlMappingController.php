<?php

namespace App\Http\Controllers;

use App\Http\Requests\GlMapping\SaveGlMappingRequest;
use App\Http\Requests\GlMapping\ShowGlMappingRequest;
use App\Services\GlMappingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class GlMappingController extends Controller
{
    public function __construct(private readonly GlMappingService $glMapping) {}

    public function index(ShowGlMappingRequest $request): Response
    {
        return Inertia::render('Accounting/GlMapping/Index', [
            'rows' => $this->glMapping->rows(),            'canManage' => $request->user()->can('accounts.manage'),
        ]);
    }

    public function update(SaveGlMappingRequest $request): RedirectResponse
    {
        try {
            $this->glMapping->save($request->mappings());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'GL mapping saved.');
    }
}
