<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The album tags library: each tag once, with one spelling ("Matteo", never "Matte" or "Mat":
    // those are its aliases). Mostly people, also people without an account; a tag can be linked
    // to an account. Albums keep their tags as text (listings.tags), always written as in here
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            // Exact (binary): "Sara" and "Sarà" stay two tags until an admin merges them
            $table->string('name', 50)->collation('utf8mb4_bin')->unique();
            $table->json('aliases')->nullable(); // other spellings that mean this tag
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->boolean('is_new')->default(false); // added by a member: for an admin to look at
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // The library starts with every tag the albums already use
        $now = now();
        $names = DB::table('listings')->pluck('tags')
            ->flatMap(fn ($tags) => array_filter(array_map('trim', explode(',', (string) $tags))))
            ->unique()->values();
        foreach ($names as $name) {
            DB::table('tags')->insert(['name' => mb_substr($name, 0, 50), 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
