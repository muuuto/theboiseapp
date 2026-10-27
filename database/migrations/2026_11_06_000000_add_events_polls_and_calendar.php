<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Events (a dinner, a party: planned like a trip, never an album) with RSVP, polls on trips and
    // events, and each person's private calendar link
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            if (! Schema::hasColumn('trips', 'kind')) {
                $table->string('kind', 10)->default('trip'); // trip | event
                $table->time('start_time')->nullable(); // events: 20:30
            }
        });
        Schema::table('trip_user', function (Blueprint $table) {
            if (! Schema::hasColumn('trip_user', 'rsvp')) {
                $table->string('rsvp', 5)->nullable(); // events: yes | maybe | no (empty: not answered)
            }
        });
        Schema::create('trip_polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('question', 200);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('trip_poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_poll_id')->constrained()->cascadeOnDelete();
            $table->string('text', 100);
            $table->timestamps();
        });
        Schema::create('trip_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_poll_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['trip_poll_option_id', 'user_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'calendar_token')) {
                $table->string('calendar_token', 64)->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_poll_votes');
        Schema::dropIfExists('trip_poll_options');
        Schema::dropIfExists('trip_polls');
        Schema::table('trips', fn (Blueprint $table) => $table->dropColumn(['kind', 'start_time']));
        Schema::table('trip_user', fn (Blueprint $table) => $table->dropColumn('rsvp'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('calendar_token'));
    }
};
