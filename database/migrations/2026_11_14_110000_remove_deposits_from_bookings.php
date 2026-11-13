<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bookings are now paid in full in one step: no deposits, partial
     * payments, deferred revenue or later revenue recognition.
     */
    public function up(): void
    {
        DB::table('gl_mappings')->whereIn('role', ['booking_deferred_revenue', 'booking_deposit_payment'])->delete();

        Schema::table('bookable_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deferred_revenue_account_id');
            $table->dropConstrainedForeignId('deposit_payment_account_id');
            $table->dropColumn('deposit_percent');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['deposit_amount', 'deposit_paid_at', 'balance_paid', 'payment_due_at', 'revenue_recognized_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bookable_items', function (Blueprint $table) {
            $table->decimal('deposit_percent', 5, 2)->nullable();
            $table->foreignId('deferred_revenue_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('deposit_payment_account_id')->nullable()->constrained('accounts')->nullOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('deposit_amount', 12, 2)->nullable();
            $table->timestamp('deposit_paid_at')->nullable();
            $table->decimal('balance_paid', 12, 2)->nullable();
            $table->timestamp('payment_due_at')->nullable();
            $table->timestamp('revenue_recognized_at')->nullable();
        });
    }
};
