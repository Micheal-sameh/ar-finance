<?php

namespace App\Modules\Booking\Policies;

use App\Models\User;
use App\Modules\Booking\Models\Booking;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bookings.view') || $user->can('bookings.create');
    }

    public function view(User $user, Booking $booking): bool
    {
        return $user->can('bookings.view') || $booking->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('bookings.create');
    }

    /**
     * The person who made the booking can cancel their own; bookings.manage
     * covers cancelling anyone's.
     */
    public function cancel(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id || $user->can('bookings.manage');
    }

    public function manage(User $user, Booking $booking): bool
    {
        return $user->can('bookings.manage');
    }
}
