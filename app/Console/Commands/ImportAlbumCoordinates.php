<?php

namespace App\Console\Commands;

use App\Models\Listing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

// Imports the suggestions made on a PC by albums:suggest-coordinates --export.
// Only fills albums that have no coordinates yet: never overwrites what the admin set.
class ImportAlbumCoordinates extends Command
{
    protected $signature = 'albums:import-coordinates {file=database/data/album-coordinates.json}';
    protected $description = 'Import suggested album coordinates from a JSON file';

    public function handle()
    {
        $path = base_path($this->argument('file'));
        if (! File::exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $imported = 0;
        foreach (json_decode(File::get($path), true) as $id => $suggestion) {
            $listing = Listing::find($id);
            // Same album (the location text didn't change) and no coordinates set meanwhile
            if ($listing && is_null($listing->latitude) && sha1($listing->location) === $suggestion['location_hash']) {
                $listing->update([
                    'latitude' => $suggestion['latitude'],
                    'longitude' => $suggestion['longitude'],
                    'coordinates_source' => 'suggested',
                    'place_name' => $suggestion['place_name'],
                ]);
                $imported++;
            }
        }

        $this->info("Imported suggested coordinates for {$imported} albums.");

        return self::SUCCESS;
    }
}
