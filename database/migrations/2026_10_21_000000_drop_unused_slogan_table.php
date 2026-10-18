<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // "slogan" was an old copy of "slogans" (the one the site uses), always empty: removed.
    // Only if it's really empty, so no slogan can ever be lost
    public function up(): void
    {
        if (Schema::hasTable('slogan') && DB::table('slogan')->doesntExist()) {
            Schema::drop('slogan');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('slogan')) {
            Schema::create('slogan', function (Blueprint $table) {
                $table->id();
                $table->string('sloganPhrase');
                $table->string('author');
                $table->timestamps();
            });
        }
    }
};
