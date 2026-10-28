<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // When a workout started and ended (time of its day): set by "New workout" and "Done",
    // editable; the page shows the time passed while it's going
    public function up(): void
    {
        Schema::table('gym_workouts', function (Blueprint $table) {
            if (! Schema::hasColumn('gym_workouts', 'start_time')) {
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('gym_workouts', fn (Blueprint $table) => $table->dropColumn(['start_time', 'end_time']));
    }
};
