<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phone notifications: each device (phone, computer) where someone turned them on,
// and what each person wants to receive (on the phone and by email)
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('push_subscriptions')) {
            Schema::create('push_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                // The address the browser gave (long URL of Apple's, Google's or Mozilla's push service)
                $table->text('endpoint');
                $table->char('endpoint_hash', 64)->unique();
                $table->string('public_key');
                $table->string('auth_token');
                $table->string('content_encoding', 20)->default('aes128gcm');
                // "iPhone", "Android", "Mac"...: to recognise the device in the account page
                $table->string('device', 50)->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('users', 'notification_settings')) {
            Schema::table('users', fn (Blueprint $table) => $table->json('notification_settings')->nullable());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        if (Schema::hasColumn('users', 'notification_settings')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('notification_settings'));
        }
    }
};
