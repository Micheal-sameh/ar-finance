<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Not tenant-scoped: a rate for (base_currency, currency_code, rate_date)
        // is the same market fact for every tenant sharing that base currency,
        // so there's no reason to fetch or store it once per tenant.
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('base_currency', 3);
            $table->string('currency_code', 10);
            $table->decimal('rate', 20, 8);
            $table->decimal('buy_rate', 20, 8)->nullable();
            $table->decimal('sell_rate', 20, 8)->nullable();
            $table->date('rate_date');
            $table->string('source');
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['base_currency', 'currency_code', 'rate_date']);
            $table->index(['base_currency', 'rate_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
