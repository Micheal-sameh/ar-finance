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
            // `price` stays the amount actually owed (after the discount), so
            // deposits, payments and the ledger need no changes. These record
            // how it was reached: discount_value is what was typed (a percent
            // or a fixed amount), discount_amount what it came to in money.
            $table->string('discount_type', 10)->nullable()->after('price');
            $table->decimal('discount_value', 18, 2)->nullable()->after('discount_type');
            $table->decimal('discount_amount', 18, 2)->default(0)->after('discount_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'discount_amount']);
        });
    }
};
