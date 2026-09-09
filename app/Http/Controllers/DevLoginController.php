<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\DevLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Email/password sign-in used only in local dev, so the app can be
 * exercised without a live Avarewase SSO server (see routes/web.php).
 */
class DevLoginController extends Controller
{
    public function __invoke(DevLoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials, remember: true)) {
            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match a local account.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
