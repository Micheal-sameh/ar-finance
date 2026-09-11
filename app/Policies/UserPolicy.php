<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function manage(User $user): bool
    {
        return $user->can('users.manage');
    }
}
