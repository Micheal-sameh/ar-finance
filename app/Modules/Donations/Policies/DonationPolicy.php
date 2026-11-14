<?php

namespace App\Modules\Donations\Policies;

use App\Models\User;
use App\Modules\Donations\Models\Donation;

class DonationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('donations.view');
    }

    public function view(User $user, Donation $donation): bool
    {
        return $user->can('donations.view');
    }

    public function create(User $user): bool
    {
        return $user->can('donations.create');
    }

    public function cancel(User $user, Donation $donation): bool
    {
        return $user->can('donations.manage');
    }
}
