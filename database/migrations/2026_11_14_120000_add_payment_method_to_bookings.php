<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable()->after('discount_amount');
        });

        // The single "booking_payment" account becomes the account of both
        // payment methods, so existing setups keep working until edited.
        foreach (DB::table('gl_mappings')->where('role', 'booking_payment')->get() as $mapping) {
            foreach (['booking_cash_payment', 'booking_bank_payment'] as $role) {
                DB::table('gl_mappings')->updateOrInsert(
                    ['tenant_id' => $mapping->tenant_id, 'role' => $role],
                    ['account_id' => $mapping->account_id, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        }

        DB::table('gl_mappings')->where('role', 'booking_payment')->delete();

        DB::table('bookings')->whereNotNull('paid_at')->whereNull('payment_method')->update(['payment_method' => 'bank_transfer']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
