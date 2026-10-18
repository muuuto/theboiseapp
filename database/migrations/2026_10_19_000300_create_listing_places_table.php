<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // An album can be in more than one place (e.g. "Londra e Dublino"): its pins move to their own table
    public function up(): void
    {
        Schema::create('listing_places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            // Name of the place the pin points to (e.g. "Merano, Alto Adige, Italia")
            $table->string('place_name')->nullable();
            // "suggested" = guessed from the location text, waiting for the admin to confirm it
            $table->string('source', 20)->default('manual');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // Coordinates already set with the previous version (one place per album)
        DB::table('listings')->whereNotNull('latitude')->orderBy('id')->each(function ($listing) {
            DB::table('listing_places')->insert([
                'listing_id' => $listing->id,
                'latitude' => $listing->latitude,
                'longitude' => $listing->longitude,
                'place_name' => $listing->place_name,
                'source' => $listing->coordinates_source ?? 'manual',
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'coordinates_source', 'place_name']);
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('location');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('coordinates_source', 20)->nullable()->after('longitude');
            $table->string('place_name')->nullable()->after('coordinates_source');
        });

        // Back to one place per album: the first one
        DB::table('listing_places')->where('position', 0)->orderBy('id')->each(function ($place) {
            DB::table('listings')->where('id', $place->listing_id)->update([
                'latitude' => $place->latitude,
                'longitude' => $place->longitude,
                'coordinates_source' => $place->source,
                'place_name' => $place->place_name,
            ]);
        });

        Schema::dropIfExists('listing_places');
    }
};
