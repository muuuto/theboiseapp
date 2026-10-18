<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Models\ListingPlace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

// Imports the places suggested on a PC by albums:suggest-coordinates --export.
// Never undoes the admin's review:
// - albums that still have one of the suggested places get the missing ones added
//   (e.g. "Londra e Dublino" already on London gets Dublin)
// - albums without places are left alone (the admin may have removed a wrong guess), unless
//   --include-empty is given, for a fresh database that was never reviewed
class ImportAlbumCoordinates extends Command
{
    protected $signature = 'albums:import-coordinates
        {file=database/data/album-coordinates.json}
        {--include-empty : Also fill albums without any place (only on a database never reviewed)}';
    protected $description = 'Import suggested album places from a JSON file, without undoing the review';

    public function handle()
    {
        $path = base_path($this->argument('file'));
        if (! File::exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $filled = 0;
        $completed = 0;
        foreach (json_decode(File::get($path), true) as $id => $suggestion) {
            $listing = Listing::with('places')->find($id);
            // Same album: its location text didn't change since the suggestion was made
            if (! $listing || sha1($listing->location) !== $suggestion['location_hash']) {
                continue;
            }

            $suggested = collect($suggestion['places']);
            $current = $listing->places;

            if ($current->isEmpty()) {
                if ($this->option('include-empty')) {
                    $this->addPlaces($listing, $suggested, 0);
                    $filled++;
                }
                continue;
            }

            // Still on one of the suggestions (confirmed or not): add the other suggested places
            $kept = $suggested->contains(fn ($place) => $current->contains(fn ($existing) => $this->samePlace($existing, $place)));
            $missing = $suggested->reject(fn ($place) => $current->contains(fn ($existing) => $this->samePlace($existing, $place)));
            if ($kept && $missing->isNotEmpty()) {
                $this->addPlaces($listing, $missing, $current->max('position') + 1);
                $completed++;
            }
        }

        $this->info("Added the missing places to {$completed} albums" . ($this->option('include-empty') ? ", filled {$filled} albums without places." : '.'));

        return self::SUCCESS;
    }

    private function addPlaces(Listing $listing, $places, int $position): void
    {
        foreach ($places->values() as $index => $place) {
            $listing->places()->create([
                'latitude' => $place['latitude'],
                'longitude' => $place['longitude'],
                'place_name' => $place['place_name'],
                'source' => 'suggested',
                'position' => $position + $index,
            ]);
        }
    }

    // Within about a kilometre
    private function samePlace(ListingPlace $existing, array $place): bool
    {
        return abs($existing->latitude - $place['latitude']) < 0.01 && abs($existing->longitude - $place['longitude']) < 0.01;
    }
}
