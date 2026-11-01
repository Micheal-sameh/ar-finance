<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each booking type offered on an item can have its own revenue account;
     * where it is null the item's own revenue account is used.
     */
    public function up(): void
    {
        Schema::table('bookable_item_booking_type', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookable_item_booking_type', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });
    }
};
