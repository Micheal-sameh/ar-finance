<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user()->load('tenant');

        return Inertia::render('Profile/Show', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'tenant_name' => $user->tenant?->name,
                'membership_code' => $user->avarewase_membership_code,
                'roles' => $user->getRoleNames(),
            ],
        ]);
    }
}
