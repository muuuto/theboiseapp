<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Tickets, bookings, PDFs of a trip: only its members can open them (the files are private,
    // in storage/app/trip-documents, never in the public folder)
    public function up(): void
    {
        Schema::create('trip_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            // Whose it is (Anna's boarding pass), or nobody's in particular
            $table->foreignId('for_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedInteger('size'); // bytes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_documents');
    }
};
