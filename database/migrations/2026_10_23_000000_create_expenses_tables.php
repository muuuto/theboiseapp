<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Shared expenses (like Splitwise): groups of people, expenses split between them, payments to settle up.
// Money is stored in cents (no rounding errors), euro only
return new class extends Migration
{
    public function up(): void
    {
        // "groups" is a reserved word in MySQL 8
        Schema::create('expense_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Optional: the album of the trip
            $table->foreignId('listing_id')->nullable()->constrained('listings')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('expense_group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['expense_group_id', 'user_id']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_group_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('amount'); // cents
            $table->foreignId('paid_by')->constrained('users');
            $table->string('split_type', 10); // equal, exact, shares
            $table->date('spent_on');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // What each person owes for an expense (also when split equally: the cents are already divided)
        Schema::create('expense_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedInteger('amount'); // cents
            $table->unsignedInteger('shares')->nullable(); // split by shares: e.g. 2 nights
            $table->unique(['expense_id', 'user_id']);
        });

        // Settle up: "Zecca paid Anna €20"
        Schema::create('expense_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users');
            $table->foreignId('to_user_id')->constrained('users');
            $table->unsignedInteger('amount'); // cents
            $table->date('paid_on');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_payments');
        Schema::dropIfExists('expense_shares');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_group_user');
        Schema::dropIfExists('expense_groups');
    }
};
