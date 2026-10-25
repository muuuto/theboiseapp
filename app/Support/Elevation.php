<?php

namespace App\Support;

use App\Models\ListingPlace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

// Altitude of album places, from Open-Meteo (free, no account; 90 m terrain data): the ground
// at the pin, so an album pinned on the village gets the village's height, not the summit's
class Elevation
{
    // Fills the places still without one; quietly gives up if the service doesn't answer
    // (the nightly albums:import-elevations tries again). Returns how many got one
    public static function fill(Collection $places): int
    {
        if (! Schema::hasColumn('listing_places', 'elevation')) {
            return 0;
        }

        $filled = 0;
        foreach ($places->whereNull('elevation')->chunk(100) as $chunk) {
            try {
                $response = Http::timeout(5)->acceptJson()->get('https://api.open-meteo.com/v1/elevation', [
                    'latitude' => $chunk->pluck('latitude')->implode(','),
                    'longitude' => $chunk->pluck('longitude')->implode(','),
                ]);
                $heights = $response->successful() ? $response->json('elevation') : null;
            } catch (\Throwable) {
                $heights = null;
            }
            if (! is_array($heights) || count($heights) !== $chunk->count()) {
                continue;
            }
            foreach ($chunk->values() as $i => $place) {
                if (is_numeric($heights[$i])) {
                    $place->update(['elevation' => (int) round($heights[$i])]);
                    $filled++;
                }
            }
        }

        return $filled;
    }
}
