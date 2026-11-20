<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // The one tenant whose ledger holds the treasurer's safe and banks.
            $table->boolean('is_treasury')->default(false)->after('is_active');
        });

        Schema::create('treasury_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treasury_tenant_id')->constrained('tenants')->restrictOnDelete();
            // The tenant that deposits, withdraws, is funded or repays; null for an internal move.
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->string('status', 20);
            $table->decimal('requested_amount', 18, 2);
            $table->decimal('accepted_amount', 18, 2)->nullable();
            $table->date('transfer_date');
            // The cash/bank account in the tenant ledger the money leaves or arrives in.
            $table->foreignId('tenant_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            // The safe/bank in the treasury ledger (the source, for an internal move).
            $table->foreignId('treasury_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            // The destination safe/bank of an internal move.
            $table->foreignId('counter_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->text('reject_reason')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['treasury_tenant_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_transfers');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('is_treasury');
        });
    }
};
