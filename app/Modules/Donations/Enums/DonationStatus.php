<?php

namespace App\Modules\Donations\Enums;

enum DonationStatus: string
{
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::Cancelled => 'Cancelled',
        };
    }
}
