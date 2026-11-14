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
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Null for an anonymous donation.
            $table->foreignId('donor_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cost_center_id')->constrained()->restrictOnDelete();
            // `amount` is in the donation's own currency; `base_amount` is
            // what was posted to the ledger in the tenant's base currency.
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->decimal('base_amount', 18, 2);
            $table->date('donated_at');
            $table->string('payment_method', 20);
            $table->string('payment_reference', 100)->nullable();
            $table->string('status', 20);
            $table->text('notes')->nullable();
            $table->unsignedInteger('receipt_number');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'receipt_number']);
            $table->index(['tenant_id', 'donated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
