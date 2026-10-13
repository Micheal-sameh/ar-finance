<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rate type (per hour / flat) is now chosen per booking type on each
        // item, next to its price, rather than once for the whole item.
        Schema::table('bookable_item_booking_type', function (Blueprint $table) {
            $table->string('rate_type', 20)->default('hourly')->after('booking_type_id');
        });

        // Carry over the item's old rate type to the types already attached.
        DB::statement('update bookable_item_booking_type set rate_type = (select rate_type from bookable_items where bookable_items.id = bookable_item_booking_type.bookable_item_id)');

        // The item itself no longer has a rate or price of its own.
        Schema::table('bookable_items', function (Blueprint $table) {
            $table->string('rate_type', 20)->nullable()->default(null)->change();
            $table->decimal('price', 18, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookable_items', function (Blueprint $table) {
            $table->string('rate_type', 20)->default('hourly')->nullable(false)->change();
            $table->decimal('price', 18, 2)->nullable(false)->change();
        });

        Schema::table('bookable_item_booking_type', function (Blueprint $table) {
            $table->dropColumn('rate_type');
        });
    }
};
