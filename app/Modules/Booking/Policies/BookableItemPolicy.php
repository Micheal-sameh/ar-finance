<?php

namespace App\Modules\Booking\Policies;

use App\Models\User;
use App\Modules\Booking\Models\BookableItem;

class BookableItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bookable_items.view');
    }

    public function view(User $user, BookableItem $item): bool
    {
        return $user->can('bookable_items.view');
    }

    public function create(User $user): bool
    {
        return $user->can('bookable_items.manage');
    }

    public function update(User $user, BookableItem $item): bool
    {
        return $user->can('bookable_items.manage');
    }

    public function delete(User $user, BookableItem $item): bool
    {
        return $user->can('bookable_items.manage');
    }
}
