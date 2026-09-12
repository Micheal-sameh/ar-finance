<?php

namespace App\Services\ExchangeRates;

use App\Exceptions\ExchangeRateProviderException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Scrapes the Central Bank of Egypt's own published buy/sell rate table —
 * the actual official quote, not a third-party market aggregator. CBE has
 * no public API; this page is the only freely-fetchable source of the real
 * numbers (its historical-data page is a session/anti-forgery-token-gated
 * form this app doesn't drive a browser to fill in, so this provider only
 * ever returns CBE's *latest published* day — fine for "today", not a
 * source of real history).
 *
 * Fragile by nature: it depends on CBE's page markup. If CBE changes their
 * site this will start throwing and {@see ExchangeRateService} falls back
 * to the market-rate provider — it never hard-fails the page.
 */
class CbeScrapeProvider implements ExchangeRateProviderInterface
{
    private const URL = 'https://www.cbe.org.eg/en/economic-research/statistics/cbe-exchange-rates';

    /**
     * CBE's published full currency names → ISO 4217 code, and a divisor
     * for the handful quoted per-100-units (currently only the Yen).
     *
     * @var array<string, array{code: string, divisor: int}>
     */
    private const CURRENCIES = [
        'US Dollar' => ['code' => 'USD', 'divisor' => 1],
        'Euro' => ['code' => 'EUR', 'divisor' => 1],
        'Pound Sterling' => ['code' => 'GBP', 'divisor' => 1],
        'Canadian Dollar' => ['code' => 'CAD', 'divisor' => 1],
        'Danish Krone' => ['code' => 'DKK', 'divisor' => 1],
        'Norwegian Krone' => ['code' => 'NOK', 'divisor' => 1],
        'Swedish Krona' => ['code' => 'SEK', 'divisor' => 1],
        'Swiss Franc' => ['code' => 'CHF', 'divisor' => 1],
        'Japanese Yen 100' => ['code' => 'JPY', 'divisor' => 100],
        'Saudi Riyal' => ['code' => 'SAR', 'divisor' => 1],
        'Kuwaiti Dinar' => ['code' => 'KWD', 'divisor' => 1],
        'UAE Dirham' => ['code' => 'AED', 'divisor' => 1],
        'Australian Dollar' => ['code' => 'AUD', 'divisor' => 1],
        'Bahraini Dinar' => ['code' => 'BHD', 'divisor' => 1],
        'Omani Riyal' => ['code' => 'OMR', 'divisor' => 1],
        'Qatari Riyal' => ['code' => 'QAR', 'divisor' => 1],
        'Jordanian Dinar' => ['code' => 'JOD', 'divisor' => 1],
        'Chinese Yuan' => ['code' => 'CNY', 'divisor' => 1],
    ];

    /**
     * CBE only ever shows the latest published day — asking it for
     * something further back than this just means "give up, fall back".
     */
    private const MAX_STALENESS_DAYS = 6;

    /**
     * The canonical currency picklist for the whole app — every currency
     * this app can actually get an exchange rate for, and nothing else.
     * Single source of truth: any `<select>` for a currency (Client, Bank
     * Account, Invoice, ...) should be built from this, not a separately
     * maintained list that could drift out of sync with what the Exchange
     * Rates page covers.
     *
     * @return array<string, string> ISO code => display name, sorted by code
     */
    public static function supportedCurrencies(): array
    {
        $options = [];

        foreach (self::CURRENCIES as $name => $meta) {
            $options[$meta['code']] = str_replace(' 100', '', $name);
        }

        ksort($options);

        return $options;
    }

    public function fetch(string $baseCurrency, string $date): array
    {
        if (strtoupper($baseCurrency) !== 'EGP') {
            throw ExchangeRateProviderException::unavailable($date);
        }

        if (Carbon::parse($date)->diffInDays(Carbon::now(), true) > self::MAX_STALENESS_DAYS) {
            throw ExchangeRateProviderException::unavailable($date);
        }

        try {
            // CBE's WAF (F5/Volterra) rejects requests that don't look like
            // a real browser navigation — a User-Agent alone isn't enough,
            // it checks for a full realistic header set too.
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                    'Sec-Fetch-Dest' => 'document',
                    'Sec-Fetch-Mode' => 'navigate',
                    'Sec-Fetch-Site' => 'none',
                    'Sec-Fetch-User' => '?1',
                ])
                ->withOptions(['version' => 1.1])
                ->get(self::URL);
        } catch (Throwable) {
            throw ExchangeRateProviderException::unavailable($date);
        }

        if (! $response->successful()) {
            throw ExchangeRateProviderException::unavailable($date);
        }

        $html = $response->body();

        if (! preg_match('/Rates for Date:\s*(\d{2})\/(\d{2})\/(\d{4})/', $html, $dateMatch)) {
            throw ExchangeRateProviderException::unavailable($date);
        }

        $asOf = "{$dateMatch[3]}-{$dateMatch[2]}-{$dateMatch[1]}";

        if (! preg_match_all(
            '#<td[^>]*>\s*([^<]+?)\s*</td>\s*<td[^>]*>\s*([\d.]+)\s*</td>\s*<td[^>]*>\s*([\d.]+)\s*</td>#s',
            $html,
            $rowMatches,
            PREG_SET_ORDER,
        )) {
            throw ExchangeRateProviderException::unavailable($date);
        }

        $rates = [];
        foreach ($rowMatches as $row) {
            $name = trim($row[1]);
            $meta = self::CURRENCIES[$name] ?? null;

            if ($meta === null) {
                continue;
            }

            $buy = round((float) $row[2] / $meta['divisor'], 8);
            $sell = round((float) $row[3] / $meta['divisor'], 8);

            if ($buy <= 0 || $sell <= 0) {
                continue;
            }

            $rates[$meta['code']] = [
                'rate' => round(($buy + $sell) / 2, 8),
                'buy' => $buy,
                'sell' => $sell,
            ];
        }

        if ($rates === []) {
            throw ExchangeRateProviderException::unavailable($date);
        }

        return ['as_of' => $asOf, 'source' => 'cbe.org.eg', 'rates' => $rates];
    }
}
