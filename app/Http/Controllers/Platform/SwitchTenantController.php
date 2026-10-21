<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;

class SwitchTenantController extends Controller
{
    public function switch(Tenant $tenant): RedirectResponse
    {
        session(['acting_tenant_id' => $tenant->id]);

        AuditLog::create([
            'tenant_id' => $tenant->id,
            'user_id' => auth()->id(),
            'auditable_type' => Tenant::class,
            'auditable_id' => $tenant->id,
            'action' => 'impersonated',
            'before' => null,
            'after' => null,
        ]);

        return redirect()->route('dashboard')->with('success', "Now working in {$tenant->name}.");
    }

    public function stop(): RedirectResponse
    {
        session()->forget('acting_tenant_id');

        return redirect()->route('platform.dashboard');
    }
}
