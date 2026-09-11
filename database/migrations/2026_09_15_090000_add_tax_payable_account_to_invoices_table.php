<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Nullable, and only required at send() time if any line has
            // tax — same conditionally-required pattern as payroll_runs'
            // deductions_payable_account_id.
            $table->foreignId('tax_payable_account_id')->nullable()->after('receivable_account_id')->constrained('accounts');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_payable_account_id');
        });
    }
};
