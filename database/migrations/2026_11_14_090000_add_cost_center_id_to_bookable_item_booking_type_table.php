<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each booking type offered on an item can have its own cost/profit
     * center; where it is null the item's own cost center is used.
     */
    public function up(): void
    {
        Schema::table('bookable_item_booking_type', function (Blueprint $table) {
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookable_item_booking_type', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_center_id');
        });
    }
};
