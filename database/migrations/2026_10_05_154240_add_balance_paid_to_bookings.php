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
            // Balance collected in partial installments after the deposit,
            // on top of deposit_amount — both sit in the item's deferred
            // revenue account until the booking is fully settled (see
            // BookingService::markPaid()).
            $table->decimal('balance_paid', 18, 2)->default(0)->after('deposit_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('balance_paid');
        });
    }
};
