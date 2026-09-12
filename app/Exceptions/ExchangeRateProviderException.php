<?php

namespace App\Exceptions;

use RuntimeException;

class ExchangeRateProviderException extends RuntimeException
{
    public static function unavailable(string $date): self
    {
        return new self("Could not fetch exchange rates for {$date} — the rate provider is unavailable or has no data for that date.");
    }
}
