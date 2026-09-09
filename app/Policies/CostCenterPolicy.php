<?php

namespace App\Policies;

use App\Models\CostCenter;
use App\Models\User;

class CostCenterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cost_centers.view');
    }

    public function view(User $user, CostCenter $costCenter): bool
    {
        return $user->can('cost_centers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('cost_centers.manage');
    }

    public function update(User $user, CostCenter $costCenter): bool
    {
        return $user->can('cost_centers.manage');
    }

    public function delete(User $user, CostCenter $costCenter): bool
    {
        return $user->can('cost_centers.manage');
    }
}
