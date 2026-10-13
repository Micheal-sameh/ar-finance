<?php

namespace App\Modules\Booking\Exceptions;

use RuntimeException;

class BookingConflictException extends RuntimeException
{
    public static function slotTaken(string $itemName): self
    {
        return new self("\"{$itemName}\" is already booked for that time slot.");
    }
}
