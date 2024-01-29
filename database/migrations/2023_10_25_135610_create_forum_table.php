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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('author')->unsigned();
            $table->foreign('author')->references('id')->on('users')
               ->onDelete('cascade');
            $table->string('title');
            $table->longText('description');
            $table->string('cover')->nullable();
            $table->string('tags');
            $table->longText('content');
            $table->longText('attachments');
            $table->integer('counter');
            $table->timestamps();
        });

        Schema::create('post_comments', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->bigInteger('post_id')->unsigned();
            $table->mediumText("comment");
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')
               ->onDelete('cascade');
            $table->foreign('post_id')->references('id')->on('posts')
               ->onDelete('cascade');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('post_id')->unsigned();
            $table->foreign('post_id')->references('id')->on('posts')
                ->onDelete('cascade');
            $table->string('title');
            $table->longText('description');
            $table->string('logo')->nullable();
            $table->string('tags');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('post_comments');
    }
};
