<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Checks the password only — the user is not logged in until the
     * two-factor step (see TwoFactorChallengeController) succeeds.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $user = User::findByLoginIdentifier($request->validated('identifier'));

        // Hash::check runs even without a user so a miss costs the same as a bad password.
        $passwordMatches = Hash::check($request->validated('password'), $user?->password ?? Hash::make('placeholder'));

        if (! $user || ! $user->password || ! $passwordMatches) {
            throw ValidationException::withMessages([
                'identifier' => 'These credentials do not match our records.',
            ]);
        }

        if ($user->status === UserStatus::Suspended) {
            throw ValidationException::withMessages([
                'identifier' => 'Your account has been suspended. Contact your administrator.',
            ]);
        }

        // MFA is skipped in local development so seeded accounts stay usable.
        if (app()->environment('local')) {
            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        $request->session()->regenerate();
        $request->session()->put([
            'two_factor.user_id' => $user->id,
            'two_factor.remember' => $request->boolean('remember'),
        ]);

        return redirect()->route('two-factor.show');
    }
}
