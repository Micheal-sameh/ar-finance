<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('description');
            $table->string('reference')->nullable();
            $table->string('source_type', 20);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'date']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
