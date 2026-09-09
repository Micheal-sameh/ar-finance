<?php

namespace App\Listeners;

use App\Models\User;
use Avarewase\SsoClient\Events\AvarewaseUserAuthenticated;
use Illuminate\Support\Facades\Log;

/**
 * Avarewase Auth is the source of truth for role assignment — this app
 * never edits roles locally. The SSO client authenticates via a normal
 * Laravel session rather than a bearer token on every request, so the
 * "resolve role claims and sync to Spatie" step from the spec happens
 * once here, right after login, instead of in per-request middleware.
 *
 * Reads a `roles` array (if the SSO server's /api/userinfo response
 * includes one — see AvarewaseUserInfo::$raw) and syncs it onto the
 * user's Spatie roles 1:1, so `$user->can(...)` checks stay in sync
 * without a local role-editing UI.
 */
class SyncAvarewaseRoleClaims
{
    public function handle(AvarewaseUserAuthenticated $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $claimedRoles = $event->userInfo->raw['roles'] ?? null;

        if (! is_array($claimedRoles) || $claimedRoles === []) {
            Log::info('Avarewase SSO userinfo carried no role claim; leaving existing role assignment untouched', [
                'user_id' => $user->id,
            ]);

            return;
        }

        $user->syncRoles($claimedRoles);
    }
}
