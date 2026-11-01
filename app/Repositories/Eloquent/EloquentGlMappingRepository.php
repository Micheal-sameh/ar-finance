<?php

namespace App\Repositories\Eloquent;

use App\Enums\GlMappingRole;
use App\Models\GlMapping;
use App\Repositories\Contracts\GlMappingRepositoryInterface;
use App\Support\TenantContext;
use Illuminate\Support\Collection;

class EloquentGlMappingRepository implements GlMappingRepositoryInterface
{
    public function accountIdsByRole(): Collection
    {
        return GlMapping::query()
            ->get(['role', 'account_id'])
            ->mapWithKeys(fn (GlMapping $mapping) => [$mapping->role->value => $mapping->account_id]);
    }

    public function set(GlMappingRole $role, int $accountId): void
    {
        GlMapping::query()->updateOrCreate(
            ['tenant_id' => app(TenantContext::class)->id(), 'role' => $role],
            ['account_id' => $accountId],
        );
    }

    public function clear(GlMappingRole $role): void
    {
        GlMapping::query()->where('role', $role)->get()->each->delete();
    }
}
