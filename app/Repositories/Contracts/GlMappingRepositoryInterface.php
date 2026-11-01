<?php

namespace App\Repositories\Contracts;

use App\Enums\GlMappingRole;
use Illuminate\Support\Collection;

interface GlMappingRepositoryInterface
{
    /**
     * The tenant's mapped account ids.
     *
     * @return Collection<string, int> Keyed by role value.
     */
    public function accountIdsByRole(): Collection;

    public function set(GlMappingRole $role, int $accountId): void;

    public function clear(GlMappingRole $role): void;
}
