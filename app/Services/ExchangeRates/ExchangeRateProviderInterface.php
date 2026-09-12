<?php

namespace App\Services\ExchangeRates;

use App\Exceptions\ExchangeRateProviderException;

interface ExchangeRateProviderInterface
{
    /**
     * Rates against $baseCurrency for currencies this provider covers.
     * Rates are "units of base currency per 1 unit of currency" (the same
     * convention Invoice::exchange_rate already uses) — e.g. for base EGP
     * this is what the Central Bank of Egypt publishes as its EGP quote.
     *
     * A provider is free to return data for a date other than the one
     * requested (e.g. a "latest published" feed asked for today when
     * today's rate isn't out yet) — the actual date covered is reported
     * back in `as_of` rather than assumed to match $date.
     *
     * @return array{
     *     as_of: string,
     *     source: string,
     *     rates: array<string, array{rate: float, buy: float|null, sell: float|null}>,
     * }
     *
     * @throws ExchangeRateProviderException
     */
    public function fetch(string $baseCurrency, string $date): array;
}
