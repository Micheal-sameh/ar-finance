<?php

namespace App\Modules\Donations\Enums;

enum DonationStatus: string
{
    case Received = 'received';
    case CancelRequested = 'cancel_requested';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::CancelRequested => 'Cancel requested',
            self::Cancelled => 'Cancelled',
        };
    }
}
