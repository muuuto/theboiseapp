<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// Euro exchange rates of the European Central Bank (via frankfurter.app: free, no account).
// A day's rate never changes: kept once fetched. Weekends and holidays get the last working day's
class ExchangeRates
{
    // The currencies the ECB publishes, the most likely on our trips first
    public const CURRENCIES = [
        'GBP', 'USD', 'CHF', 'CZK', 'HUF', 'PLN', 'DKK', 'SEK', 'NOK', 'ISK', 'RON', 'BGN', 'TRY',
        'JPY', 'CNY', 'HKD', 'SGD', 'THB', 'IDR', 'MYR', 'PHP', 'KRW', 'INR', 'ILS',
        'AUD', 'NZD', 'CAD', 'MXN', 'BRL', 'ZAR',
    ];

    // ['rate' => units of the currency for 1 euro, 'date' => the ECB day used], or null if unavailable
    public static function rate(string $currency, string $date): ?array
    {
        if (! in_array($currency, self::CURRENCIES, true)) {
            return null;
        }
        // Not published yet for today or the future: the latest
        $day = $date >= now('Europe/Rome')->toDateString() ? 'latest' : $date;
        $key = "ecb-rate:{$currency}:{$day}";
        if ($day !== 'latest' && ($cached = Cache::get($key))) {
            return $cached;
        }

        try {
            $response = Http::timeout(6)->acceptJson()->get("https://api.frankfurter.app/{$day}", ['from' => 'EUR', 'to' => $currency]);
            $rate = $response->json("rates.{$currency}");
        } catch (\Throwable) {
            return null;
        }
        if (! $response->successful() || ! is_numeric($rate) || $rate <= 0) {
            return null;
        }

        $result = ['rate' => (float) $rate, 'date' => (string) $response->json('date')];
        // A past day's rate is final; "latest" is asked again next time
        Cache::put($key, $result, $day === 'latest' ? now()->addHours(3) : now()->addYear());

        return $result;
    }
}
