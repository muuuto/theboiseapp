<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Demo account for recruiters: read-only, no forum, no emails
            $table->boolean('is_demo')->default(false)->after('isAdmin');
            // null = follow the device, 0 = light, 1 = dark
            $table->boolean('dark_mode')->nullable()->default(null)->change();
        });

        // dark_mode was never settable: everyone starts on "follow the device"
        DB::table('users')->update(['dark_mode' => null]);

        // The demo account was hard-coded as user 28 "Guest"
        DB::table('users')->where('id', 28)->where('name', 'Guest')->update(['is_demo' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_demo');
            $table->boolean('dark_mode')->nullable()->default(false)->change();
        });
    }
};
