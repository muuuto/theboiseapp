<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// "All albums | My albums": empty = not chosen (all albums).
// It was on/off for everyone: the "off" ones become "not chosen" (whoever switched it on keeps it)
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'only_own_albums')) {
            return;
        }
        Schema::table('users', fn (Blueprint $table) => $table->boolean('only_own_albums')->nullable()->default(null)->change());
        DB::table('users')->where('only_own_albums', false)->update(['only_own_albums' => null]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'only_own_albums')) {
            return;
        }
        DB::table('users')->whereNull('only_own_albums')->update(['only_own_albums' => false]);
        Schema::table('users', fn (Blueprint $table) => $table->boolean('only_own_albums')->nullable(false)->default(false)->change());
    }
};
