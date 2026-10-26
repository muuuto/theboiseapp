<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Replies to comments (albums and forum): the comment they answer, one level deep (a reply to
    // a reply joins the same thread). No foreign key: the old comment tables predate migrations
    public function up(): void
    {
        foreach (['comments', 'post_comments'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (! Schema::hasColumn($name, 'parent_id')) {
                    $table->unsignedBigInteger('parent_id')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['comments', 'post_comments'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('parent_id'));
        }
    }
};
