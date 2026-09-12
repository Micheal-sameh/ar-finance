<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface ExchangeRateRepositoryInterface
{
    /**
     * Stored rates for every currency on an exact date, keyed by currency_code.
     */
    public function forDate(string $baseCurrency, string $date): Collection;

    /**
     * Most recent rate_date on or before $date that has any stored rates —
     * used to fall back to the last business day (weekends/holidays have
     * nothing to fetch).
     */
    public function latestDateOnOrBefore(string $baseCurrency, string $date): ?string;

    /**
     * @param array<int, array{base_currency: string, currency_code: string, rate: float, rate_date: string, source: string, fetched_at: string}> $rows
     */
    public function upsertMany(array $rows): void;
}
