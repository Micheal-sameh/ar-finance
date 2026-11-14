<?php

namespace App\Modules\Donations\Policies;

use App\Models\User;
use App\Modules\Donations\Models\Donor;

class DonorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('donations.view');
    }

    public function create(User $user): bool
    {
        return $user->can('donations.create');
    }

    public function update(User $user, Donor $donor): bool
    {
        return $user->can('donations.manage');
    }
}
