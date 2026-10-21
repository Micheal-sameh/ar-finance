<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookable_items', function (Blueprint $table) {
            // Null/0 = this item doesn't accept deposits; a booking must be
            // paid in full. Otherwise the percentage of the booking's price
            // collectible upfront, deferred until the booking is fully paid.
            $table->decimal('deposit_percent', 5, 2)->nullable()->after('price');
            $table->foreignId('deferred_revenue_account_id')->nullable()->after('account_id')->constrained('accounts')->nullOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('deposit_amount', 18, 2)->nullable()->after('price');
            $table->timestamp('deposit_paid_at')->nullable()->after('deposit_amount');
            // Deadline to pay the remaining balance after the deposit —
            // past this with the booking still unpaid, it's overdue
            // (flagged for staff, nothing automatic happens to the slot).
            $table->dateTime('payment_due_at')->nullable()->after('deposit_paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['deposit_amount', 'deposit_paid_at', 'payment_due_at']);
        });

        Schema::table('bookable_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deferred_revenue_account_id');
            $table->dropColumn('deposit_percent');
        });
    }
};
