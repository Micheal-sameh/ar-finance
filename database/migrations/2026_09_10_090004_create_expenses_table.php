<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            // The expense-category account (Chart of Accounts doubles as
            // the category list — no separate categories taxonomy).
            $table->foreignId('account_id')->constrained();
            $table->decimal('amount', 18, 2);
            $table->date('date');
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            // The AP control account approving this expense credits —
            // same explicit-account philosophy as invoices.receivable_account_id.
            $table->foreignId('payable_account_id')->constrained('accounts');
            $table->string('receipt_path')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
