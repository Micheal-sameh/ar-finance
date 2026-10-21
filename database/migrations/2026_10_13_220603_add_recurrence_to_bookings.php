<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // A repeating booking is stored as one ordinary booking per
            // occurrence (so conflicts, payments and cancelling all work per
            // date); these columns just record how the series was made.
            $table->string('recurrence', 20)->default('none')->after('notes');
            $table->date('recurrence_until')->nullable()->after('recurrence');
            $table->uuid('recurrence_group_id')->nullable()->after('recurrence_until')->index();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['recurrence_group_id']);
            $table->dropColumn(['recurrence', 'recurrence_until', 'recurrence_group_id']);
        });
    }
};
