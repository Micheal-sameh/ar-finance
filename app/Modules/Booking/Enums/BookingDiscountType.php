<?php

namespace App\Modules\Booking\Enums;

enum BookingDiscountType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Percent',
            self::Fixed => 'Fixed amount',
        };
    }
}
