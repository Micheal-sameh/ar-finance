<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $tenantContext = app(TenantContext::class);

        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'permissions' => $user?->getAllPermissions()->pluck('name') ?? [],
                'roles' => $user?->getRoleNames() ?? [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'platform' => $user?->isPlatformAdmin() ? [
                'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name', 'is_active']),
                'activeTenantId' => $tenantContext->id(),
                'isImpersonating' => $tenantContext->isImpersonating(),
            ] : null,
        ]);
    }
}
