<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The reusable catalog of kinds of booking (Wedding Pray, Holy Mass,
        // Meeting …) — defined once, then offered on any bookable item.
        Schema::create('booking_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        // Which types a bookable item offers, and what each costs there —
        // the same type can be priced differently per item.
        Schema::create('bookable_item_booking_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bookable_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 18, 2);
            $table->timestamps();

            $table->unique(['bookable_item_id', 'booking_type_id'], 'item_type_unique');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('booking_type_id')->nullable()->after('bookable_item_id')->constrained('booking_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_type_id');
        });

        Schema::dropIfExists('bookable_item_booking_type');
        Schema::dropIfExists('booking_types');
    }
};
