<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Order of forum categories and posts chosen by the admins (drag and drop).
// Empty until someone reorders: then the forum keeps today's order (categories by title, posts newest first)
return new class extends Migration
{
    public function up(): void
    {
        foreach (['categories', 'posts'] as $table) {
            if (! Schema::hasColumn($table, 'position')) {
                Schema::table($table, fn (Blueprint $table) => $table->unsignedInteger('position')->nullable());
            }
        }
    }

    public function down(): void
    {
        foreach (['categories', 'posts'] as $table) {
            if (Schema::hasColumn($table, 'position')) {
                Schema::table($table, fn (Blueprint $table) => $table->dropColumn('position'));
            }
        }
    }
};
