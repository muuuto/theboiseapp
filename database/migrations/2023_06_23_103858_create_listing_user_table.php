<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('listing_user', function (Blueprint $table) {

            $table->bigInteger('user_id')->unsigned();
        
            $table->bigInteger('listing_id')->unsigned();
        
            $table->foreign('user_id')->references('id')->on('users')
        
                ->onDelete('cascade');
        
            $table->foreign('listing_id')->references('id')->on('listings')
        
                ->onDelete('cascade');
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_user');
    }
};
