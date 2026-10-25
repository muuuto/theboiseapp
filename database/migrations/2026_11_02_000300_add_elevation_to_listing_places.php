<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Altitude of each album place in metres (Wrapped: the highest place of the year). Filled
    // from Open-Meteo when an album is saved, and for the old ones by albums:import-elevations
    public function up(): void
    {
        Schema::table('listing_places', function (Blueprint $table) {
            if (! Schema::hasColumn('listing_places', 'elevation')) {
                $table->smallInteger('elevation')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('listing_places', fn (Blueprint $table) => $table->dropColumn('elevation'));
    }
};
