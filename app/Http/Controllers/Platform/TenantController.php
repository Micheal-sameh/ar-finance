<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\SaveTenantRequest;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantService $tenants,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Tenant::class);

        return Inertia::render('Platform/Tenants/Index', [
            'tenants' => $this->tenants->paginate(),
        ]);
    }

    public function store(SaveTenantRequest $request): RedirectResponse
    {
        $this->authorize('manage', Tenant::class);

        $this->tenants->create($request->validated());

        return redirect()->route('platform.tenants.index')->with('success', 'Tenant created with the standard chart of accounts.');
    }

    public function update(SaveTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('manage', Tenant::class);

        $this->tenants->update($tenant, $request->validated());

        return redirect()->route('platform.tenants.index')->with('success', 'Tenant updated.');
    }
}
