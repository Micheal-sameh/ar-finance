<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\User;
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
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ($user->status === UserStatus::Suspended || $this->belongsToDeactivatedTenant($user))) {
            auth()->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Your account has been suspended. Contact your administrator.');
        }

        return $next($request);
    }

    /**
     * Platform-level accounts have no tenant of their own, so only ordinary
     * users are cut off when their tenant is deactivated.
     */
    private function belongsToDeactivatedTenant(User $user): bool
    {
        if ($user->tenant_id === null || $user->isPlatformAdmin()) {
            return false;
        }

        return Tenant::query()->whereKey($user->tenant_id)->where('is_active', false)->exists();
    }
}
