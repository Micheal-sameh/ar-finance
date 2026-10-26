<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            // Set when a reconciliation is finished; a reconciled
            // transaction is locked (cannot be unmatched).
            $table->foreignId('bank_reconciliation_id')->nullable()->after('matched_journal_line_id')
                ->constrained('bank_reconciliations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_reconciliation_id');
        });
    }
};
