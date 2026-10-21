<?php

namespace App\Modules\Booking\Policies;

use App\Models\User;
use App\Modules\Booking\Models\BookingType;

class BookingTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bookable_items.view');
    }

    public function create(User $user): bool
    {
        return $user->can('bookable_items.manage');
    }

    public function update(User $user, BookingType $type): bool
    {
        return $user->can('bookable_items.manage');
    }

    public function delete(User $user, BookingType $type): bool
    {
        return $user->can('bookable_items.manage');
    }
}
