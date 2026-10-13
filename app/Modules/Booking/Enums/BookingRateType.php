<?php

namespace App\Modules\Booking\Enums;

enum BookingRateType: string
{
    case Hourly = 'hourly';
    case Flat = 'flat';

    public function label(): string
    {
        return match ($this) {
            self::Hourly => 'Per Hour',
            self::Flat => 'Flat Rate',
        };
    }
}
