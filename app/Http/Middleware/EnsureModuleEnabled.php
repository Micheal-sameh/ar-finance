<?php

namespace App\Http\Middleware;

use App\Enums\TenantModule;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides a module from tenants it has not been switched on for: usage is
 * `module:donations`. It runs after `tenant`, so the tenant being acted in
 * is already known — for a Platform Admin that is the one they switched into.
 */
class EnsureModuleEnabled
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $enabled = TenantModule::tryFrom($module);

        abort_unless($enabled !== null && $this->tenantContext->tenant()?->hasModule($enabled), 404);

        return $next($request);
    }
}
