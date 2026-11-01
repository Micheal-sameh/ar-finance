<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('role', 40);
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_mappings');
    }
};
