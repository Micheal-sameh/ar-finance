<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_lines', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default(0)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('bill_lines', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
        });
    }
};
