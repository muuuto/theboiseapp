<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // The real place on the map; `location` stays the free-text label shown on the album
            $table->decimal('latitude', 10, 7)->nullable()->after('location');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            // "suggested" = guessed from the location text, waiting for the admin to confirm it
            $table->string('coordinates_source', 20)->nullable()->after('longitude');
            // Name of the place the coordinates point to (e.g. "Merano, Alto Adige, Italia")
            $table->string('place_name')->nullable()->after('coordinates_source');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'coordinates_source', 'place_name']);
        });
    }
};
