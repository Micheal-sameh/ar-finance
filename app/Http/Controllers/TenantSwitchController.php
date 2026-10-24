<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Lets an ordinary multi-tenant user change which of their tenants they are
 * working in. Platform Admins use the impersonation routes instead.
 */
class TenantSwitchController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant): RedirectResponse
    {
        $user = $request->user();

        abort_if($user->isPlatformAdmin(), 403);
        abort_unless($tenant->is_active && $user->canAccessTenant($tenant->id), 403);

        $request->session()->put('active_tenant_id', $tenant->id);

        return redirect()->route('dashboard')->with('success', "Now working in {$tenant->name}.");
    }
}
