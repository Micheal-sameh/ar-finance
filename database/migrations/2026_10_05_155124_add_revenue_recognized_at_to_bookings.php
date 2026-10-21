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
        Schema::table('bookings', function (Blueprint $table) {
            // A fully-paid booking's money sits in the item's deferred
            // revenue account (same as the deposit) until the event itself
            // has finished — see RecognizeBookingRevenue. Null = not
            // recognized yet, whether or not the booking is paid.
            $table->timestamp('revenue_recognized_at')->nullable()->after('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('revenue_recognized_at');
        });
    }
};
