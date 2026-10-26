<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->timestamps();

            $table->unique(['tenant_id', 'year']);
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            // Null budgets the account for the whole service rather than one cost center.
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            // One amount per calendar month, in the account's own direction
            // (revenue earned / expense incurred), matching the P&L.
            foreach (range(1, 12) as $month) {
                $table->decimal("m{$month}", 18, 2)->default(0);
            }
            $table->timestamps();

            $table->index(['budget_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
    }
};
