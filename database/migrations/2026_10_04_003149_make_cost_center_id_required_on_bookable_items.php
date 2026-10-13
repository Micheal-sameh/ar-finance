<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookable_items', function (Blueprint $table) {
            $table->dropForeign(['cost_center_id']);
            $table->dropColumn('cost_center_id');
        });

        Schema::table('bookable_items', function (Blueprint $table) {
            $table->foreignId('cost_center_id')->after('account_id')->constrained('cost_centers');
        });
    }

    public function down(): void
    {
        Schema::table('bookable_items', function (Blueprint $table) {
            $table->dropForeign(['cost_center_id']);
            $table->dropColumn('cost_center_id');
        });

        Schema::table('bookable_items', function (Blueprint $table) {
            $table->foreignId('cost_center_id')->nullable()->after('account_id')->constrained('cost_centers')->nullOnDelete();
        });
    }
};
