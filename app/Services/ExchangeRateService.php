<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    /**
     * ČNB daily rates cached per request to avoid multiple API calls.
     *
     * @var array<string, array<string, float>>
     */
    private array $cnbDailyCache = [];

    public function getRate(string $baseCurrency, string $targetCurrency, ?Carbon $date = null): ?float
    {
        if ($baseCurrency === $targetCurrency) {
            return 1.0;
        }

        $date = $date ?? now();
        $dateString = $date->toDateString();

        $cached = ExchangeRate::query()
            ->where('base_currency', $baseCurrency)
            ->where('target_currency', $targetCurrency)
            ->where('date', $dateString)
            ->first();

        if ($cached) {
            return (float) $cached->rate;
        }

        return $this->fetchAndStore($baseCurrency, $targetCurrency, $date);
    }

    public function fetchAndStore(string $baseCurrency, string $targetCurrency, ?Carbon $date = null): ?float
    {
        $date = $date ?? now();

        $rate = $this->fetchFromCnb($baseCurrency, $targetCurrency, $date);

        if ($rate !== null) {
            ExchangeRate::updateOrCreate(
                [
                    'base_currency' => $baseCurrency,
                    'target_currency' => $targetCurrency,
                    'date' => $date->toDateString(),
                ],
                [
                    'rate' => $rate,
                    'source' => 'cnb',
                ]
            );

            return $rate;
        }

        return null;
    }

    /**
     * Fetch exchange rate from the Czech National Bank API.
     *
     * ČNB rates are CZK-centric: each rate tells how many CZK per 1 unit (or per `amount` units) of the foreign currency.
     * For cross-rates (e.g. EUR→USD), we calculate through CZK.
     */
    private function fetchFromCnb(string $baseCurrency, string $targetCurrency, Carbon $date): ?float
    {
        try {
            $rates = $this->getCnbDailyRates($date);

            if ($rates === null) {
                return null;
            }

            // ČNB rates: 1 foreign currency = X CZK
            // CZK itself is implicitly 1.0
            $baseToCzk = $baseCurrency === 'CZK' ? 1.0 : ($rates[$baseCurrency] ?? null);
            $targetToCzk = $targetCurrency === 'CZK' ? 1.0 : ($rates[$targetCurrency] ?? null);

            if ($baseToCzk === null || $targetToCzk === null) {
                Log::warning('ČNB rate not found for currency pair', [
                    'base' => $baseCurrency,
                    'target' => $targetCurrency,
                    'available' => array_keys($rates),
                ]);

                return null;
            }

            // Cross rate: base→CZK / target→CZK = base→target
            // Example: EUR→USD = (EUR→CZK) / (USD→CZK) = 25.12 / 23.45 = 1.0712
            // Example: EUR→CZK = 25.12 / 1.0 = 25.12
            return $baseToCzk / $targetToCzk;
        } catch (\Throwable $e) {
            Log::error('ČNB exchange rate fetch failed', [
                'base' => $baseCurrency,
                'target' => $targetCurrency,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get all ČNB daily rates for a given date, normalized to "1 unit = X CZK".
     *
     * @return array<string, float>|null
     */
    private function getCnbDailyRates(Carbon $date): ?array
    {
        $dateString = $date->toDateString();

        if (isset($this->cnbDailyCache[$dateString])) {
            return $this->cnbDailyCache[$dateString];
        }

        $response = Http::get('https://api.cnb.cz/cnbapi/exrates/daily', [
            'date' => $date->format('Y-m-d'),
            'lang' => 'EN',
        ]);

        if (! $response->successful()) {
            Log::error('ČNB API request failed', [
                'status' => $response->status(),
                'date' => $dateString,
            ]);

            return null;
        }

        $data = $response->json();
        $rates = [];

        foreach ($data['rates'] ?? [] as $entry) {
            $code = $entry['currencyCode'] ?? null;
            $rate = $entry['rate'] ?? null;
            $amount = $entry['amount'] ?? 1;

            if ($code && $rate) {
                // Normalize to per-1-unit: if amount=100 and rate=15.5, then 1 unit = 0.155 CZK
                $rates[$code] = (float) $rate / (float) $amount;
            }
        }

        $this->cnbDailyCache[$dateString] = $rates;

        return $rates;
    }

    /**
     * Prefetch common rates for today and store in DB.
     *
     * @param  array<string>  $currencies
     */
    public function fetchCommonRates(string $baseCurrency = 'CZK', array $currencies = ['EUR', 'USD', 'GBP', 'PLN', 'HUF']): void
    {
        foreach ($currencies as $currency) {
            $this->fetchAndStore($currency, $baseCurrency);
        }
    }
}
