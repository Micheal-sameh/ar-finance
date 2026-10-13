<?php

namespace App\Modules\Booking\Enums;

use Carbon\CarbonImmutable;

enum BookingRecurrence: string
{
    case None = 'none';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Does not repeat',
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
        };
    }

    /**
     * The $occurrence-th repeat after $start (0 = $start itself). Always
     * counted from the original date rather than from the previous repeat,
     * so a booking on the 31st doesn't drift to the 28th for good after
     * February.
     */
    public function shift(CarbonImmutable $start, int $occurrence): CarbonImmutable
    {
        return match ($this) {
            self::None => $start,
            self::Daily => $start->addDays($occurrence),
            self::Weekly => $start->addWeeks($occurrence),
            self::Monthly => $start->addMonthsNoOverflow($occurrence),
            self::Yearly => $start->addYearsNoOverflow($occurrence),
        };
    }
}
