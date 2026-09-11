<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // The GL cash/bank account this bank account reconciles
            // against — same explicit-control-account philosophy as
            // invoices/bills/payroll.
            $table->foreignId('account_id')->constrained();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
