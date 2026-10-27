<?php

namespace App\Support;

use App\Models\Trip;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// The forecast for an upcoming trip or event, from Open-Meteo (free, no account): the place is
// found from its "Where" text, then the days of the trip that are within the next 16 days.
// Cached (the place for a month, the forecast for 3 hours); quietly nothing if unavailable
class Weather
{
    public const DAYS_AHEAD = 15;

    // ['place' => 'Palermo, Italia', 'days' => [['date', 'code', 'max', 'min', 'rain'], …]] or null
    public static function forTrip(Trip $trip): ?array
    {
        if (! $trip->destination || ! $trip->date_from) {
            return null;
        }
        $today = now('Europe/Rome')->startOfDay();
        $from = $trip->date_from->copy()->max($today);
        $to = ($trip->date_to ?? $trip->date_from)->copy()->min($today->copy()->addDays(self::DAYS_AHEAD));
        if ($from->gt($to)) {
            return null; // over, or still too far away
        }
        $place = self::place($trip->destination);
        if (! $place) {
            return null;
        }
        $days = self::forecast($place['latitude'], $place['longitude'], $from->toDateString(), $to->toDateString());

        return $days ? ['place' => $place['name'], 'days' => $days] : null;
    }

    // "Palermo and around" → the first place Open-Meteo knows ("Palermo")
    private static function place(string $where): ?array
    {
        $tries = collect([$where, preg_split('/,| e | and | - /u', $where)[0]])->map(fn ($text) => trim($text))->filter()->unique();
        foreach ($tries as $text) {
            $found = Cache::remember('geo:' . md5(mb_strtolower($text)), now()->addDays(30), function () use ($text) {
                try {
                    $result = Http::timeout(5)->acceptJson()->get('https://geocoding-api.open-meteo.com/v1/search', ['name' => $text, 'count' => 1, 'language' => app()->getLocale()])->json('results.0');
                } catch (\Throwable) {
                    return null;
                }

                return $result ? ['latitude' => $result['latitude'], 'longitude' => $result['longitude'], 'name' => trim(($result['name'] ?? $text) . ', ' . ($result['country'] ?? ''), ', ')] : false;
            });
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    private static function forecast(float $latitude, float $longitude, string $from, string $to): ?array
    {
        $key = 'weather:' . round($latitude, 2) . ':' . round($longitude, 2) . ":{$from}:{$to}";

        return Cache::remember($key, now()->addHours(3), function () use ($latitude, $longitude, $from, $to) {
            try {
                $daily = Http::timeout(5)->acceptJson()->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
                    'timezone' => 'auto',
                    'start_date' => $from,
                    'end_date' => $to,
                ])->json('daily');
            } catch (\Throwable) {
                return null;
            }
            if (! is_array($daily['time'] ?? null)) {
                return null;
            }

            return collect($daily['time'])->map(fn ($date, $i) => [
                'date' => $date,
                'code' => (int) ($daily['weather_code'][$i] ?? 0),
                'max' => (int) round($daily['temperature_2m_max'][$i] ?? 0),
                'min' => (int) round($daily['temperature_2m_min'][$i] ?? 0),
                'rain' => $daily['precipitation_probability_max'][$i] ?? null,
            ])->values()->all();
        });
    }

    // For notifications: "☀️ 24° / 15°"
    public static function emoji(int $code): string
    {
        return match (true) {
            $code === 0 => '☀️',
            $code <= 2 => '🌤️',
            $code === 3 => '☁️',
            $code <= 48 => '🌫️',
            $code <= 67, $code >= 80 && $code <= 82 => '🌧️',
            $code <= 77, $code === 85, $code === 86 => '❄️',
            default => '⛈️',
        };
    }
}
