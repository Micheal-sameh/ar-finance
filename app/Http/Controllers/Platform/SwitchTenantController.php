<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;

class SwitchTenantController extends Controller
{
    public function switch(Tenant $tenant): RedirectResponse
    {
        session(['acting_tenant_id' => $tenant->id]);

        return redirect()->route('dashboard')->with('success', "Now working in {$tenant->name}.");
    }

    public function stop(): RedirectResponse
    {
        session()->forget('acting_tenant_id');

        return redirect()->route('platform.dashboard');
    }
}
