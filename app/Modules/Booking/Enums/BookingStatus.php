<?php

namespace App\Modules\Booking\Enums;

enum BookingStatus: string
{
    case Confirmed = 'confirmed';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmed',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }
}
