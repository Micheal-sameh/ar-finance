<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('received_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('amount', 18, 2);
            $table->date('received_on');
            // The bank/cash account the money landed in (debited).
            $table->foreignId('deposit_account_id')->constrained('accounts');
            // What the money is for (credited): a revenue account, or a
            // liability such as Customer Deposits for an advance.
            $table->foreignId('credit_account_id')->constrained('accounts');
            $table->string('reference', 100)->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'received_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('received_payments');
    }
};
