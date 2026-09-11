<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            // Input VAT — recoverable, so an Asset-type account, not a
            // liability. Nullable, only required at approve() time if
            // any line has tax.
            $table->foreignId('tax_receivable_account_id')->nullable()->after('payable_account_id')->constrained('accounts');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_receivable_account_id');
        });
    }
};
