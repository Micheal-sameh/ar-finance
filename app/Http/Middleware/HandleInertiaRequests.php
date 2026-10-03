<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Collection;
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
            'devUsers' => app()->environment('local') && $user ? $this->devUsers($tenantContext) : null,
        ]);
    }

    /**
     * User list for the navbar's local-dev-only quick switcher (see
     * DevUserSwitcher.tsx and DevSwitchUserController). Combines the
     * current tenant's users with every Platform Admin / Portal Manager,
     * so the switcher covers both "regular" and "admin" personas without
     * a fresh unscoped query of the whole users table. Never computed,
     * let alone sent, outside app()->environment('local') — see the
     * caller above.
     *
     * @return array<int, array<string, mixed>>
     */
    private function devUsers(TenantContext $tenantContext): array
    {
        $tenantId = $tenantContext->id();

        $tenantUsers = $tenantId
            ? User::query()->where('tenant_id', $tenantId)->where('status', UserStatus::Active)->with('roles')->get()
            : new Collection;

        $adminUsers = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['Platform Admin', 'Portal Manager']))
            ->where('status', UserStatus::Active)
            ->with('roles')
            ->get();

        return $tenantUsers->concat($adminUsers)
            ->unique('id')
            ->sortBy('name')
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'code' => $user->avarewase_membership_code,
                'role' => $user->roles->first()?->name,
            ])
            ->values()
            ->all();
    }
}
