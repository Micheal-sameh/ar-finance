<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('pay_date');
            $table->string('status', 20)->default('draft');
            // Same explicit-control-account philosophy as invoices/bills.
            $table->foreignId('expense_account_id')->constrained('accounts');
            $table->foreignId('payable_account_id')->constrained('accounts');
            // Only used (and required at approval) if any payslip has a
            // deduction — see PayrollRunService::approve().
            $table->foreignId('deductions_payable_account_id')->nullable()->constrained('accounts');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
