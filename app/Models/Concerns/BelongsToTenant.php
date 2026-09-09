<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the authenticated user's tenant and stamps tenant_id
 * on create. Mix into every tenant-owned model — the GL and every report
 * built on it rely on this to keep tenants' financial data isolated.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id) && auth()->check()) {
                $model->tenant_id = auth()->user()->tenant_id;
            }
        });

        static::addGlobalScope(new TenantScope);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
