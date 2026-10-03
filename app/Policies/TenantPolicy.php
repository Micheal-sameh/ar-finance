<?php

namespace App\Policies;

use App\Models\User;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tenants.manage');
    }

    public function manage(User $user): bool
    {
        return $user->can('tenants.manage');
    }
}
