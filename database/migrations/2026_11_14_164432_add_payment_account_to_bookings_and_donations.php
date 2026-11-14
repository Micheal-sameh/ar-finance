<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['bookings', 'donations'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('payment_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['bookings', 'donations'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('payment_account_id');
            });
        }
    }
};
