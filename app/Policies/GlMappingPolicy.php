<?php

namespace App\Policies;

use App\Models\User;

/**
 * The GL mapping decides where postings land, so it follows the
 * chart-of-accounts permissions.
 */
class GlMappingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounts.view');
    }

    public function manage(User $user): bool
    {
        return $user->can('accounts.manage');
    }
}
