<?php

use App\Modules\Booking\Models\BookableItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every bookable item must now post to a specific center (see
        // CreateBookableItemData/SaveBookableItemRequest). Any item created
        // before that rule existed, with no center assigned, is pre-launch
        // test data — remove it (cascades to its bookings) rather than
        // guess a center for it.
        BookableItem::withoutGlobalScopes()->whereNull('cost_center_id')->get()->each->delete();

        Schema::table('bookable_items', function (Blueprint $table) {
            // The existing FK was added with ->nullOnDelete(), which MySQL
            // refuses to combine with NOT NULL — drop and recreate it as
            // restrict instead (a center in use by a bookable item can no
            // longer be deleted out from under it).
            $table->dropForeign(['cost_center_id']);
        });

        Schema::table('bookable_items', function (Blueprint $table) {
            $table->foreignId('cost_center_id')->nullable(false)->change();
            $table->foreign('cost_center_id')->references('id')->on('cost_centers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookable_items', function (Blueprint $table) {
            $table->dropForeign(['cost_center_id']);
        });

        Schema::table('bookable_items', function (Blueprint $table) {
            $table->foreignId('cost_center_id')->nullable()->change();
            $table->foreign('cost_center_id')->references('id')->on('cost_centers')->nullOnDelete();
        });
    }
};
