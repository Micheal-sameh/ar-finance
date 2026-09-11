<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('purchase_date');
            $table->decimal('cost', 18, 2);
            $table->decimal('salvage_value', 18, 2)->default(0);
            $table->unsignedSmallInteger('useful_life_years');
            $table->string('depreciation_method', 20)->default('straight_line');
            // The asset itself (e.g. "Office Equipment") — informational
            // here; its purchase is expected to already be recorded via
            // a bill/expense/journal entry. Only depreciation posts from
            // this register.
            $table->foreignId('asset_account_id')->constrained('accounts');
            $table->foreignId('depreciation_account_id')->constrained('accounts');
            $table->foreignId('accumulated_depreciation_account_id')->constrained('accounts');
            // Cached running total — only ever written by the
            // depreciation-posting code path in the same transaction as
            // the journal entry, so it can't drift from the GL.
            $table->decimal('accumulated_depreciation', 18, 2)->default(0);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
