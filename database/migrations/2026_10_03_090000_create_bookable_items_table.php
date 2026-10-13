<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookable_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            // Exclusive: one booking at a time blocks the whole slot for
            // everyone else. Shared: any number of people can book the
            // same slot concurrently (e.g. a class vs. a single therapist).
            $table->boolean('is_exclusive')->default(true);
            $table->string('rate_type', 20)->default('hourly');
            $table->decimal('price', 18, 2);
            // Revenue account/center this item's bookings post to once paid
            // — same explicit-account philosophy as expenses.account_id.
            $table->foreignId('account_id')->constrained();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookable_items');
    }
};
