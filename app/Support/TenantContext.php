<?php

namespace App\Support;

use App\Models\Tenant;
use Closure;

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

    public function id(): ?int
    {
        if ($this->overriding) {
            return $this->override;
        }

        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        return $user->isPlatformAdmin() ? session('acting_tenant_id') : $user->tenant_id;
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
