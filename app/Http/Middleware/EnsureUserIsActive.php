<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A suspended user (see UserStatus) keeps a valid SSO session but is
 * cut off from the app itself — logged out on their next request rather
 * than mid-session, since we don't revoke sessions out of band.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->status === UserStatus::Suspended) {
            auth()->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Your account has been suspended. Contact your administrator.');
        }

        return $next($request);
    }
}
