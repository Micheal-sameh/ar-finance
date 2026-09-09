<?php

namespace App\Policies;

use App\Models\Bill;
use App\Models\User;

class BillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bills.view');
    }

    public function view(User $user, Bill $bill): bool
    {
        return $user->can('bills.view');
    }

    public function create(User $user): bool
    {
        return $user->can('bills.create');
    }

    public function manage(User $user, Bill $bill): bool
    {
        return $user->can('bills.manage');
    }
}
