<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Upcoming trips: planned before they happen (date poll, checklist, shared expenses),
// then they become an album. Kept apart from albums, so plans never show up among the memories
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('destination')->nullable();
            $table->text('notes')->nullable();
            // Empty while the date poll is open
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('listing_id')->nullable()->constrained('listings')->nullOnDelete();
            $table->foreignId('expense_group_id')->nullable()->constrained('expense_groups')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('trip_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['trip_id', 'user_id']);
        });

        Schema::create('trip_date_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // "These dates work for me"
        Schema::create('trip_date_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_date_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['trip_date_option_id', 'user_id']);
        });

        // Who brings what
        Schema::create('trip_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('text');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('done')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_items');
        Schema::dropIfExists('trip_date_votes');
        Schema::dropIfExists('trip_date_options');
        Schema::dropIfExists('trip_user');
        Schema::dropIfExists('trips');
    }
};
