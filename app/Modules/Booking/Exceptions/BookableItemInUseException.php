<?php

namespace App\Modules\Booking\Exceptions;

use RuntimeException;

class BookableItemInUseException extends RuntimeException
{
    public static function hasBookings(string $name): self
    {
        return new self("\"{$name}\" cannot be deleted: it has bookings.");
    }
}
