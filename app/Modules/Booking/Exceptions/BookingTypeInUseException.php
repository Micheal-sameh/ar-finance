<?php

namespace App\Modules\Booking\Exceptions;

use RuntimeException;

class BookingTypeInUseException extends RuntimeException
{
    public static function inUse(string $name): self
    {
        return new self("\"{$name}\" cannot be deleted: it is used by a bookable item or a booking.");
    }
}
