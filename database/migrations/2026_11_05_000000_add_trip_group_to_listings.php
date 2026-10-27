<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Albums of the same trip, posted by different people: they share a group number (the id of
    // the first album of the group), and each album page shows the others
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            if (! Schema::hasColumn('listings', 'trip_group')) {
                $table->unsignedBigInteger('trip_group')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('listings', fn (Blueprint $table) => $table->dropColumn('trip_group'));
    }
};
