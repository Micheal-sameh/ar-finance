<?php

namespace App\Repositories\Eloquent;

use App\Models\ExchangeRate;
use App\Repositories\Contracts\ExchangeRateRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class EloquentExchangeRateRepository implements ExchangeRateRepositoryInterface
{
    public function forDate(string $baseCurrency, string $date): Collection
    {
        return ExchangeRate::query()
            ->where('base_currency', $baseCurrency)
            ->where('rate_date', $date)
            ->get()
            ->keyBy('currency_code');
    }

    public function latestDateOnOrBefore(string $baseCurrency, string $date): ?string
    {
        $rateDate = ExchangeRate::query()
            ->where('base_currency', $baseCurrency)
            ->where('rate_date', '<=', $date)
            ->orderByDesc('rate_date')
            ->value('rate_date');

        return $rateDate !== null ? Carbon::parse($rateDate)->toDateString() : null;
    }

    public function upsertMany(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $now = Carbon::now();
        $rows = array_map(
            fn (array $row) => $row + ['created_at' => $now, 'updated_at' => $now],
            $rows,
        );

        ExchangeRate::query()->upsert(
            $rows,
            ['base_currency', 'currency_code', 'rate_date'],
            ['rate', 'buy_rate', 'sell_rate', 'source', 'fetched_at', 'updated_at'],
        );
    }
}
