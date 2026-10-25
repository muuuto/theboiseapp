<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Expenses in another currency: £45 is kept as written, with the rate used; amount (cents of
    // euro) stays what balances and settling count. Empty currency = euro, as before
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'currency')) {
                $table->char('currency', 3)->nullable();
                $table->unsignedInteger('original_amount')->nullable(); // hundredths of that currency
                $table->decimal('exchange_rate', 16, 6)->nullable(); // that currency for 1 euro
                $table->boolean('rate_manual')->default(false); // what the card charged, not the ECB rate
            }
        });
        Schema::table('expense_shares', function (Blueprint $table) {
            if (! Schema::hasColumn('expense_shares', 'original_amount')) {
                $table->unsignedInteger('original_amount')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $table) => $table->dropColumn(['currency', 'original_amount', 'exchange_rate', 'rate_manual']));
        Schema::table('expense_shares', fn (Blueprint $table) => $table->dropColumn('original_amount'));
    }
};
