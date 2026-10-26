<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            // The bank/cash account the money left from.
            $table->foreignId('payment_account_id')->constrained('accounts');
            $table->decimal('amount', 18, 2);
            $table->date('paid_on');
            // Cheque number, transfer id, ... — free text for the user's own tracing.
            $table->string('reference', 100)->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['bill_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_payments');
    }
};
