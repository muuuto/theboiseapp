<?php

namespace App\Console\Commands;

use App\Models\ListingPlace;
use App\Support\Elevation;
use Illuminate\Console\Command;

// Altitude for the album places that don't have one yet (the old albums, or when Open-Meteo
// didn't answer while saving). Safe to run again: it skips the ones already done
class ImportAlbumElevations extends Command
{
    protected $signature = 'albums:import-elevations';
    protected $description = 'Look up the altitude of album places that don\'t have one yet';

    public function handle()
    {
        $missing = ListingPlace::whereNull('elevation')->get();
        $filled = Elevation::fill($missing);
        $this->info("Altitude found for {$filled} of {$missing->count()} places.");

        return self::SUCCESS;
    }
}
