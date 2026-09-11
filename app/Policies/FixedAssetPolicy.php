<?php

namespace App\Policies;

use App\Models\FixedAsset;
use App\Models\User;

class FixedAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('fixed_assets.view');
    }

    public function view(User $user, FixedAsset $fixedAsset): bool
    {
        return $user->can('fixed_assets.view');
    }

    public function create(User $user): bool
    {
        return $user->can('fixed_assets.manage');
    }

    public function manage(User $user, FixedAsset $fixedAsset): bool
    {
        return $user->can('fixed_assets.manage');
    }

    public function delete(User $user, FixedAsset $fixedAsset): bool
    {
        return $user->can('fixed_assets.manage');
    }
}
