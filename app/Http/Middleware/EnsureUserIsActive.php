<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A suspended user (see UserStatus) keeps a valid session but is
 * cut off from the app itself — logged out on their next request rather
 * than mid-session, since we don't revoke sessions out of band.
 */
class EnsureUserIsActive
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ($user->status === UserStatus::Suspended || $this->hasNoActiveTenant($user))) {
            auth()->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Your account has been suspended. Contact your administrator.');
        }

        return $next($request);
    }

    /**
     * Platform-level accounts have no tenant of their own, so only ordinary
     * users are cut off — and only when every one of their tenants is
     * deactivated. If just the tenant they are working in was deactivated,
     * they are moved to one that is still active.
     */
    private function hasNoActiveTenant(User $user): bool
    {
        if ($user->tenant_id === null || $user->isPlatformAdmin()) {
            return false;
        }

        $activeTenants = $user->tenants()->where('is_active', true)->get();

        if ($activeTenants->isEmpty()) {
            return true;
        }

        if (! $activeTenants->contains('id', $this->tenantContext->id())) {
            session(['active_tenant_id' => $activeTenants->first()->id]);
        }

        return false;
    }
}
