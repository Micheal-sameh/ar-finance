<?php

namespace App\Support;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolves the tenant_id that scoping (TenantScope/BelongsToTenant) and
 * tenant-owned services should act on. For an ordinary user this is just
 * their own tenant_id. A Platform Admin has no fixed tenant of their own —
 * their effective tenant is whichever one they've switched into via the
 * nav dropdown (session-based impersonation), or none at all while
 * viewing the cross-tenant Platform area.
 *
 * Bound as a singleton (see AppServiceProvider) so runAs()'s override is
 * visible to every service resolved during the same request/callback,
 * not just the instance that set it.
 */
class TenantContext
{
    private bool $overriding = false;

    private ?int $override = null;

    private ?Request $requestedFor = null;

    private ?int $requested = null;

    public function id(): ?int
    {
        if ($this->overriding) {
            return $this->override;
        }

        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        if ($user->isPlatformAdmin()) {
            return session('acting_tenant_id') ?? $this->requestedTenantId();
        }

        // A multi-tenant user works in whichever of their tenants they last
        // switched to, falling back to their default one.
        $active = session('active_tenant_id');

        if ($active !== null && $user->canAccessTenant((int) $active)) {
            return (int) $active;
        }

        return $user->tenant_id;
    }

    /**
     * The service a Platform Admin names for this one request, without
     * switching into it — `for_tenant` in the query string or body. Writes
     * (POST/PUT/DELETE) and JSON option lookups fall back to the same
     * param on the Referer, so a create page opened with `?for_tenant=3`
     * keeps its form submit and picker fetches scoped to service 3 without
     * every form having to echo the id back. Inertia page visits do not use
     * the Referer, so list pages stay in the combined "All services" view.
     */
    private function requestedTenantId(): ?int
    {
        $request = request();

        if ($this->requestedFor === $request) {
            return $this->requested;
        }

        $raw = $request->input('for_tenant');

        if ($raw === null && (! $request->isMethodSafe() || ($request->expectsJson() && ! $request->header('X-Inertia')))) {
            $referer = $request->headers->get('referer');

            if ($referer !== null && parse_url($referer, PHP_URL_HOST) === $request->getHost()) {
                parse_str((string) parse_url($referer, PHP_URL_QUERY), $query);
                $raw = $query['for_tenant'] ?? null;
            }
        }

        $id = is_scalar($raw) && ctype_digit((string) $raw) ? (int) $raw : null;

        $this->requestedFor = $request;
        $this->requested = $id !== null && Tenant::whereKey($id)->where('is_active', true)->exists() ? $id : null;

        return $this->requested;
    }

    public function tenant(): ?Tenant
    {
        $id = $this->id();

        return $id !== null ? Tenant::find($id) : null;
    }

    public function isPlatformAdmin(): bool
    {
        return (bool) auth()->user()?->isPlatformAdmin();
    }

    public function isImpersonating(): bool
    {
        return $this->isPlatformAdmin() && $this->id() !== null;
    }

    /**
     * True when a Platform Admin is browsing the cross-tenant "All tenants"
     * view (no active tenant) — the signal list pages use to switch into
     * their read-only, every-tenant-combined mode (see e.g.
     * AccountController::index()).
     */
    public function isViewingAllTenants(): bool
    {
        return $this->isPlatformAdmin() && $this->id() === null;
    }

    /**
     * Runs $callback with id() temporarily forced to $tenantId, regardless
     * of session state — used by the platform dashboard/reports to loop
     * over every tenant without touching the real session.
     */
    public function runAs(?int $tenantId, Closure $callback): mixed
    {
        $previousOverriding = $this->overriding;
        $previousOverride = $this->override;

        $this->overriding = true;
        $this->override = $tenantId;

        try {
            return $callback();
        } finally {
            $this->overriding = $previousOverriding;
            $this->override = $previousOverride;
        }
    }
}
