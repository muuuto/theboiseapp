<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Passkeys: log in with Face ID / fingerprint on the phone. The phone keeps the private key,
    // the site only its public key (useless to anyone who'd steal the database)
    public function up(): void
    {
        Schema::create('passkeys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('credential_id', 512)->unique(); // base64url
            $table->text('public_key'); // PEM
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->string('name', 100); // "iPhone", "Android"
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkeys');
    }
};
