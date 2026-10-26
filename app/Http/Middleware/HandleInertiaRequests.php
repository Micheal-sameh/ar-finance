<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\User;
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
        $user?->loadMissing('roles.permissions');
        $tenantContext = app(TenantContext::class);

        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'turnstile_site_key' => config('services.turnstile.site_key'),
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
            'memberTenants' => $this->memberTenants($user, $tenantContext),
            'devUsers' => app()->environment('local') && $user ? $this->devUsers($tenantContext) : null,
        ]);
    }

    /**
     * The tenant switcher for an ordinary user who belongs to more than one
     * tenant; null for everyone else (Platform Admins use the platform share).
     *
     * @return array{tenants: array<int, array{id: int, name: string}>, activeTenantId: int|null}|null
     */
    private function memberTenants(?User $user, TenantContext $tenantContext): ?array
    {
        if ($user === null || $user->isPlatformAdmin()) {
            return null;
        }

        if ($user->loadMissing('tenants')->tenants->count() < 2) {
            return null;
        }

        $tenants = $user->tenants()->where('is_active', true)->get();

        if ($tenants->count() < 2) {
            return null;
        }

        return [
            'tenants' => $tenants->sortBy('name')->map(fn ($tenant) => ['id' => $tenant->id, 'name' => $tenant->name])->values()->all(),
            'activeTenantId' => $tenantContext->id(),
        ];
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

        $users = User::query()
            ->where('status', UserStatus::Active)
            ->where(function ($query) use ($tenantId) {
                if ($tenantId) {
                    $query->whereHas('tenants', fn ($tenantQuery) => $tenantQuery->whereKey($tenantId));
                }

                $query->orWhereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('name', ['Platform Admin', 'Portal Manager']));
            })
            ->with('roles')
            ->get();

        return $users
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
