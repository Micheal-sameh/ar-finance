<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /**
     * Already-authenticated visitors skip straight past the login page.
     */
    public function __invoke(): Response|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Login', [
            'devLoginEnabled' => app()->environment('local'),
        ]);
    }
}
