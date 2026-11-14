<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // bank_transfer, instapay or pos — shared by Booking and Donations.
            $table->string('method', 20);
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'method', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};
