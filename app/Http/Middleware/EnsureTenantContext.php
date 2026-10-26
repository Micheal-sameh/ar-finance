<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Modules\Booking\Models\BookableItem;
use App\Support\TenantContext;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ordinary users always have a tenant. A Platform Admin has none of their
 * own, so instead of making them switch into a service first, the tenant is
 * worked out per request: from the record the route is acting on (edit,
 * delete, approve, pay...), or from an explicit `for_tenant` param (create
 * flows — see TenantContext). The rest of the request then runs as that
 * tenant. With neither, there is nothing to act on, so a create page asks
 * which service it is for and anything else bounces to the Platform
 * dashboard.
 */
class EnsureTenantContext
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tenantContext->isPlatformAdmin() || $this->tenantContext->isImpersonating()) {
            return $next($request);
        }

        $tenantId = $this->tenantOfBoundRecord($request);

        if ($tenantId !== null) {
            return $this->tenantContext->runAs($tenantId, fn () => $next($request));
        }

        if ($request->isMethod('GET') && str_ends_with((string) $request->route()?->getName(), '.create')) {
            return Inertia::render('Platform/ChooseService', [
                'tenants' => Tenant::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'continueUrl' => $request->url(),
            ])->toResponse($request);
        }

        return redirect()->route('platform.dashboard')
            ->with('error', 'Select a service to manage its data.');
    }

    /**
     * Route model binding is unscoped for a Platform Admin with no active
     * tenant (TenantScope skips), so any bound record from any service
     * resolves — its tenant_id is the tenant to act as.
     */
    private function tenantOfBoundRecord(Request $request): ?int
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model && $parameter->getAttribute('tenant_id') !== null) {
                return (int) $parameter->getAttribute('tenant_id');
            }
        }

        // Booking a slot from the combined calendar names the item, not a
        // service — the item's own tenant is the one the booking belongs to.
        $itemId = $request->input('bookable_item_id');

        if ($request->routeIs('bookings.store') && is_scalar($itemId) && ctype_digit((string) $itemId)) {
            return BookableItem::query()->whereKey((int) $itemId)->value('tenant_id');
        }

        return null;
    }
}
