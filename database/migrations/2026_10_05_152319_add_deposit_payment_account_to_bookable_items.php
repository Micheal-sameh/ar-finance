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
        Schema::table('bookable_items', function (Blueprint $table) {
            // Which asset account a deposit on this item lands in — set once
            // by the admin here instead of chosen per booking, so every
            // deposit for this item is collected into the same place.
            $table->foreignId('deposit_payment_account_id')->nullable()->after('deferred_revenue_account_id')->constrained('accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookable_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deposit_payment_account_id');
        });
    }
};
