<?php

namespace App\Services;

use App\Exceptions\ExchangeRateProviderException;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\ExchangeRateRepositoryInterface;
use App\Services\ExchangeRates\CbeScrapeProvider;
use App\Services\ExchangeRates\ExchangeRateProviderInterface;
use Illuminate\Support\Carbon;

class ExchangeRateService
{
    /**
     * Shown even for a fresh tenant with no clients yet, so the page isn't
     * empty on day one.
     */
    private const DEFAULT_CURRENCIES = ['USD', 'EUR', 'GBP', 'SAR', 'AED'];

    public function __construct(
        private readonly ExchangeRateRepositoryInterface $rates,
        private readonly CbeScrapeProvider $cbe,
        private readonly ExchangeRateProviderInterface $fallbackProvider,
        private readonly ClientRepositoryInterface $clients,
    ) {
    }

    public function baseCurrency(): string
    {
        return strtoupper(auth()->user()->tenant->base_currency ?? 'EGP');
    }

    /**
     * The currency picklist for every `<select>` in the app (Client, Bank
     * Account, Invoice, ...) — the tenant's base currency plus exactly the
     * currencies the Exchange Rates page tracks ({@see trackedCurrencies()}),
     * not the full list of currencies CBE could theoretically quote. A
     * currency should never be pickable here unless it's one this app is
     * actually fetching/showing a rate for.
     *
     * @return array<int, array{code: string, name: string}>
     */
    public function currencyOptions(): array
    {
        $base = $this->baseCurrency();
        $names = CbeScrapeProvider::supportedCurrencies();

        $baseName = $base === 'EGP' ? 'Egyptian Pound' : ($names[$base] ?? $base);
        $options = [['code' => $base, 'name' => $baseName]];

        foreach ($this->trackedCurrencies() as $code) {
            $options[] = ['code' => $code, 'name' => $names[$code] ?? $code];
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public function trackedCurrencies(): array
    {
        $base = $this->baseCurrency();

        $currencies = array_unique(array_merge(self::DEFAULT_CURRENCIES, $this->clients->distinctCurrencies()));
        $currencies = array_values(array_filter($currencies, fn (string $code) => strtoupper($code) !== $base));

        sort($currencies);

        return $currencies;
    }

    /**
     * The single rate for one currency on (or nearest before) $date —
     * built on top of {@see ratesForDate()} so it shares the same
     * sync/fallback behavior. Null if $currency isn't tracked or no rate
     * could be found at all (never fabricates one).
     */
    public function rateFor(string $currency, string $date): ?float
    {
        $currency = strtoupper($currency);

        if ($currency === $this->baseCurrency()) {
            return 1.0;
        }

        foreach ($this->ratesForDate($date)['rows'] as $row) {
            if ($row['currency'] === $currency) {
                return $row['rate'];
            }
        }

        return null;
    }

    /**
     * Rates for every tracked currency against the tenant's base currency
     * on $date, syncing from a provider first if nothing is cached yet.
     * Falls back to the nearest earlier cached date (weekends/holidays
     * aren't published) rather than showing nothing.
     *
     * @return array{
     *     base_currency: string,
     *     requested_date: string,
     *     actual_date: string,
     *     is_stale: bool,
     *     source: string|null,
     *     rows: array<int, array{currency: string, rate: float, buy: float|null, sell: float|null, inverse_rate: float}>,
     *     sync_error: string|null,
     * }
     */
    public function ratesForDate(string $date): array
    {
        $base = $this->baseCurrency();
        $currencies = $this->trackedCurrencies();

        $syncError = null;

        $cached = $this->rates->forDate($base, $date);
        $missing = array_diff($currencies, $cached->keys()->all());

        if ($missing !== []) {
            try {
                $this->sync($date);
                $cached = $this->rates->forDate($base, $date);
            } catch (ExchangeRateProviderException $e) {
                $syncError = $e->getMessage();
            }
        }

        $actualDate = $date;
        $isStale = false;

        if ($cached->isEmpty()) {
            $fallbackDate = $this->rates->latestDateOnOrBefore($base, $date);

            if ($fallbackDate !== null) {
                $actualDate = $fallbackDate;
                $isStale = $fallbackDate !== $date;
                $cached = $this->rates->forDate($base, $fallbackDate);
            }
        }

        $rows = [];
        $source = null;
        foreach ($currencies as $code) {
            $rate = $cached->get($code);

            if ($rate === null) {
                continue;
            }

            $source ??= $rate->source;
            $value = (float) $rate->rate;

            $rows[] = [
                'currency' => $code,
                'rate' => $value,
                'buy' => $rate->buy_rate !== null ? (float) $rate->buy_rate : null,
                'sell' => $rate->sell_rate !== null ? (float) $rate->sell_rate : null,
                'inverse_rate' => $value > 0 ? round(1 / $value, 8) : 0.0,
            ];
        }

        return [
            'base_currency' => $base,
            'requested_date' => $date,
            'actual_date' => $actualDate,
            'is_stale' => $isStale,
            'source' => $source,
            'rows' => $rows,
            'sync_error' => $rows === [] ? $syncError : null,
        ];
    }

    /**
     * Forces a fetch for $date, overwriting any cached rates for whichever
     * date the provider actually returns data for. Tries the real CBE
     * quote first (EGP only, and only if $date is recent — CBE's page has
     * no real history without a browser-driven session); falls back to the
     * market-rate provider, walking backward up to 7 days if even that has
     * nothing for $date (weekends/holidays aren't published anywhere).
     *
     * @throws ExchangeRateProviderException
     */
    public function sync(string $date): void
    {
        $base = $this->baseCurrency();

        try {
            $this->store($base, $this->cbe->fetch($base, $date));

            return;
        } catch (ExchangeRateProviderException) {
            // Not EGP, too old for CBE's latest-only page, or CBE's site
            // is unreachable/changed shape — fall through to the market
            // rate provider below rather than failing the whole sync.
        }

        $cursor = Carbon::parse($date);
        $attempts = 0;

        while ($attempts < 7) {
            try {
                $this->store($base, $this->fallbackProvider->fetch($base, $cursor->toDateString()));

                return;
            } catch (ExchangeRateProviderException) {
                $cursor->subDay();
                $attempts++;
            }
        }

        throw ExchangeRateProviderException::unavailable($date);
    }

    /**
     * @param array{as_of: string, source: string, rates: array<string, array{rate: float, buy: float|null, sell: float|null}>} $result
     */
    private function store(string $base, array $result): void
    {
        $now = Carbon::now()->toDateTimeString();

        $rows = [];
        foreach ($result['rates'] as $code => $rate) {
            $rows[] = [
                'base_currency' => $base,
                'currency_code' => $code,
                'rate' => $rate['rate'],
                'buy_rate' => $rate['buy'],
                'sell_rate' => $rate['sell'],
                'rate_date' => $result['as_of'],
                'source' => $result['source'],
                'fetched_at' => $now,
            ];
        }

        $this->rates->upsertMany($rows);
    }
}
