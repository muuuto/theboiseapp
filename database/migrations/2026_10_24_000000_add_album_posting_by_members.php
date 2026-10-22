<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Other people can post albums (when the admins switch it on), and what happens to albums is recorded
return new class extends Migration
{
    public function up(): void
    {
        // Site switches changed from the maintenance page (e.g. "members can post albums")
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'can_post_albums')) {
                $table->boolean('can_post_albums')->default(false);
            }
            // The "Only albums I posted" toggle of the album list and map
            if (! Schema::hasColumn('users', 'only_own_albums')) {
                $table->boolean('only_own_albums')->default(false);
            }
        });

        // History: who created, edited or deleted which album, and who got the permission.
        // No foreign key on the album: the history stays after it's deleted (with its title)
        if (! Schema::hasTable('album_events')) {
            Schema::create('album_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('listing_id')->nullable()->index();
                $table->string('listing_title')->nullable();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action', 30);
                $table->json('details')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('album_events');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['can_post_albums', 'only_own_albums']);
        });
        Schema::dropIfExists('settings');
    }
};
