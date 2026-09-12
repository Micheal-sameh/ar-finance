<?php

namespace App\Services\ExchangeRates;

use App\Exceptions\ExchangeRateProviderException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Free, keyless daily rate feed (fawazahmed0/exchange-api, jsdelivr-hosted).
 * Not an official Central Bank of Egypt feed — used as the fallback for
 * dates {@see CbeScrapeProvider} can't cover (CBE's own site only ever
 * shows the latest published day, no real history without a browser-driven
 * session), and for any base currency other than EGP.
 */
class FreeCurrencyApiProvider implements ExchangeRateProviderInterface
{
    private const HOSTS = [
        'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@%s/v1/currencies/%s.json',
        'https://%s.currency-api.pages.dev/v1/currencies/%s.json',
    ];

    public function fetch(string $baseCurrency, string $date): array
    {
        $base = strtolower($baseCurrency);

        foreach (self::HOSTS as $urlTemplate) {
            try {
                $response = Http::timeout(8)->get(sprintf($urlTemplate, $date, $base));
            } catch (Throwable) {
                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            $payload = $response->json($base);

            if (! is_array($payload) || $payload === []) {
                continue;
            }

            $rates = [];
            foreach ($payload as $code => $rate) {
                // The feed also carries crypto tickers (AAVE, 1INCH, ...) —
                // this app only deals in fiat, so keep ISO-4217-shaped codes.
                if (! preg_match('/^[a-z]{3}$/i', (string) $code)) {
                    continue;
                }

                if (is_numeric($rate) && (float) $rate > 0) {
                    // Provider convention is "units of $code per 1 unit of
                    // base" — invert to "units of base per 1 unit of $code"
                    // to match this app's exchange_rate convention.
                    $value = round(1 / (float) $rate, 8);
                    $rates[strtoupper($code)] = ['rate' => $value, 'buy' => null, 'sell' => null];
                }
            }

            if ($rates !== []) {
                return ['as_of' => $date, 'source' => 'fawazahmed0/exchange-api', 'rates' => $rates];
            }
        }

        throw ExchangeRateProviderException::unavailable($date);
    }
}
