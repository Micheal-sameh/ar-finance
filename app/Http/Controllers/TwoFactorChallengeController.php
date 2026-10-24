<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Second half of sign-in. Users who already confirmed an authenticator get
 * a code challenge; everyone else is made to enrol one here, so no account
 * can reach the app on a password alone.
 */
class TwoFactorChallengeController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly Totp $totp) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasConfirmedTwoFactor()) {
            return Inertia::render('Auth/TwoFactorChallenge');
        }

        // Reloading keeps the same unconfirmed secret so a scan already done stays valid.
        $secret = $user->two_factor_secret ?? $this->totp->generateSecret();
        $user->forceFill(['two_factor_secret' => $secret])->save();

        return Inertia::render('Auth/TwoFactorSetup', [
            'secret' => $secret,
            'otpauthUri' => $this->totp->provisioningUri($secret, $user->email ?? $user->phone ?? $user->name, config('app.name')),
        ]);
    }

    public function store(TwoFactorChallengeRequest $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $attempts = (int) $request->session()->get('two_factor.attempts', 0) + 1;
        $request->session()->put('two_factor.attempts', $attempts);

        if ($attempts > self::MAX_ATTEMPTS) {
            $this->clearPending($request);

            return redirect()->route('login')->with('error', 'Too many incorrect codes. Please sign in again.');
        }

        $code = $request->validated('code');
        $enrolling = ! $user->hasConfirmedTwoFactor();

        if (! $this->codeIsValid($user, $code, $enrolling)) {
            throw ValidationException::withMessages(['code' => 'That code is not valid.']);
        }

        $recoveryCodes = $enrolling ? $user->confirmTwoFactor() : null;

        $remember = (bool) $request->session()->get('two_factor.remember');
        $this->clearPending($request);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        if ($recoveryCodes) {
            return redirect()->route('two-factor.recovery-codes')->with('recovery_codes', $recoveryCodes);
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Shown once, right after enrolment, to the now-logged-in user.
     */
    public function recoveryCodes(Request $request): Response|RedirectResponse
    {
        $codes = $request->session()->get('recovery_codes');

        if (! $codes) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/RecoveryCodes', ['codes' => $codes]);
    }

    private function codeIsValid(User $user, string $code, bool $enrolling): bool
    {
        $normalized = preg_replace('/\s+/', '', $code);

        if ($this->totp->verify($user->two_factor_secret, $normalized)) {
            // A TOTP code is single-use within its validity window.
            return Cache::add("two-factor:used:{$user->id}:{$normalized}", true, 120);
        }

        return ! $enrolling && $user->consumeRecoveryCode($code);
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get('two_factor.user_id');

        return $id ? User::query()->find($id) : null;
    }

    private function clearPending(Request $request): void
    {
        $request->session()->forget(['two_factor.user_id', 'two_factor.remember', 'two_factor.attempts']);
    }
}
