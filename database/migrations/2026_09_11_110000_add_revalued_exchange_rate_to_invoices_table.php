<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Null until the first revaluation/settlement re-measures the
            // receivable — until then the booked rate is just exchange_rate.
            // See Invoice::bookedExchangeRate().
            $table->decimal('revalued_exchange_rate', 18, 8)->nullable()->after('exchange_rate');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('revalued_exchange_rate');
        });
    }
};
