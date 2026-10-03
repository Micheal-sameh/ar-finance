<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A Platform Admin has no tenant of their own, so the ordinary
 * tenant-scoped routes (accounts, journals, every module) are meaningless
 * to them until they switch into a tenant from the nav dropdown. Bounce
 * them to the Platform dashboard instead of letting every module render
 * empty/unscoped.
 */
class EnsureTenantContext
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->tenantContext->isPlatformAdmin() && ! $this->tenantContext->isImpersonating()) {
            return redirect()->route('platform.dashboard')
                ->with('error', 'Select a tenant to manage its data.');
        }

        return $next($request);
    }
}
