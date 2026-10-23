<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Each person's hometown, where their trips start from (Wrapped kilometres). Empty = Bolzano
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'home_name')) {
                $table->string('home_name', 100)->nullable();
                $table->decimal('home_latitude', 10, 7)->nullable();
                $table->decimal('home_longitude', 10, 7)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['home_name', 'home_latitude', 'home_longitude']));
    }
};
