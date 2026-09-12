<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained();
            $table->string('invoice_number');
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('status', 20)->default('draft');
            $table->string('currency', 3)->default('EGP');
            $table->decimal('exchange_rate', 12, 6)->default(1);
            // The AR control account this invoice's balance posts to —
            // explicit rather than inferred, same philosophy as manual
            // journal entries: the user always sees which account moves.
            $table->foreignId('receivable_account_id')->constrained('accounts');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'invoice_number']);
            $table->index(['tenant_id', 'status']);
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
