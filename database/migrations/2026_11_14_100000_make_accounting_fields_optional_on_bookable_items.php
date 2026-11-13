<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Booking staff define items without choosing accounts; finance/admin
     * fill in the revenue account and cost center afterwards (or the GL
     * mapping supplies the revenue account when the item is created).
     */
    public function up(): void
    {
        Schema::table('bookable_items', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->change();
            $table->unsignedBigInteger('cost_center_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookable_items', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable(false)->change();
            $table->unsignedBigInteger('cost_center_id')->nullable(false)->change();
        });
    }
};
