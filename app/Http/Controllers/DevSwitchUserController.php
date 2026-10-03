<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\DevSwitchUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Instant "log in as" switcher for local dev only (see DevUserSwitcher.tsx
 * in the navbar). No password check — DevSwitchUserRequest::authorize()
 * and the route registration in routes/web.php both re-gate this to
 * app()->environment('local') as well, same defense-in-depth pattern as
 * DevLoginController.
 */
class DevSwitchUserController extends Controller
{
    public function __invoke(DevSwitchUserRequest $request, User $user): RedirectResponse
    {
        Auth::login($user);

        $request->session()->regenerate();
        $request->session()->forget('acting_tenant_id');

        return redirect()->route('dashboard')->with('success', "Switched to {$user->name}.");
    }
}
