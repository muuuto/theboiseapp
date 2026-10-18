<?php

namespace App\Console\Commands;

use App\Models\Listing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

// Guesses the coordinates of albums from their location text with OpenStreetMap (Nominatim).
// Runs on a PC (it takes ~1 second per album, too slow for the hosting): --export writes the
// result to a file that the server imports with albums:import-coordinates.
class SuggestAlbumCoordinates extends Command
{
    protected $signature = 'albums:suggest-coordinates {--export= : Also write the suggestions to this JSON file}';
    protected $description = 'Suggest coordinates for albums without them, from their location text';

    public function handle()
    {
        $found = [];

        foreach (Listing::whereNull('latitude')->orderBy('id')->get() as $listing) {
            $result = $this->lookup($listing->location);

            if ($result) {
                $suggestion = [
                    'latitude' => round((float) $result['lat'], 7),
                    'longitude' => round((float) $result['lon'], 7),
                    'coordinates_source' => 'suggested',
                    'place_name' => mb_substr($result['display_name'], 0, 255),
                ];
                $listing->update($suggestion);
                // The repository is public: a fingerprint of the location text, never the text itself
                $found[$listing->id] = ['location_hash' => sha1($listing->location)] + $suggestion;
                $this->line("#{$listing->id} {$listing->location} → {$suggestion['place_name']}");
            } else {
                $this->line("#{$listing->id} {$listing->location} → not found");
            }
        }

        if ($path = $this->option('export')) {
            File::put($path, json_encode($found, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            $this->info("Exported to {$path}");
        }

        $this->info(count($found) . ' albums got suggested coordinates.');

        return self::SUCCESS;
    }

    // Most albums are near home: a place in Trentino-Alto Adige wins over the rest of the world...
    // unless the worldwide result is much more important (Bologna the city, not "Case Bologna" near
    // Treviso). Texts like "Casa di Matt, Terlano" or "Funes & Passo delle Erbe" are simplified when not found
    private function lookup(string $location): ?array
    {
        $candidates = array_unique(array_filter([
            trim($location),
            trim(last(explode(',', $location))),
            trim(preg_split('/\s*(?:&|,|\se\s)\s*/u', $location)[0]),
        ]));

        foreach ($candidates as $query) {
            $nearHome = $this->search($query, '10.3,47.1,12.5,45.6');
            $world = $this->search($query, null);

            if ($nearHome && $world && ($world['importance'] ?? 0) > ($nearHome['importance'] ?? 0) + 0.2) {
                return $world;
            }
            if ($nearHome || $world) {
                return $nearHome ?? $world;
            }
        }

        return null;
    }

    private function search(string $query, ?string $viewbox): ?array
    {
        // Nominatim usage policy: identify the app, at most one request per second
        sleep(1);

        try {
            $results = Http::withHeaders(['User-Agent' => 'theboise.it album map (' . config('app.url') . ')'])
                ->timeout(15)
                ->get('https://nominatim.openstreetmap.org/search', array_filter([
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 5,
                    'accept-language' => 'it,en',
                    'viewbox' => $viewbox,
                    'bounded' => $viewbox ? 1 : null,
                    // Near home means Italy: the box also touches Austria (a "Milano" hotel in Sölden...)
                    'countrycodes' => $viewbox ? 'it' : null,
                ]))
                ->throw()
                ->json();
        } catch (Throwable $e) {
            $this->warn("{$query}: lookup failed ({$e->getMessage()})");

            return null;
        }

        // Streets are often named after towns ("Via Tirano" in Bormio is not Tirano)
        return collect($results)->first(fn ($result) => ($result['category'] ?? null) !== 'highway');
    }
}
