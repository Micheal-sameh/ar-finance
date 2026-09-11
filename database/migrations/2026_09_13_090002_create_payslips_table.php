<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained();
            $table->decimal('gross_pay', 18, 2);
            $table->decimal('deductions', 18, 2)->default(0);
            $table->decimal('net_pay', 18, 2);
            $table->timestamps();

            $table->index('payroll_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
