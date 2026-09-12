<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('base_currency', 3)->default('EGP')->change();
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->string('currency', 3)->default('EGP')->change();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('currency', 3)->default('EGP')->change();
        });
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('currency', 3)->default('EGP')->change();
        });

        DB::table('tenants')->where('base_currency', 'USD')->update(['base_currency' => 'EGP']);
        DB::table('clients')->where('currency', 'USD')->update(['currency' => 'EGP']);
        DB::table('invoices')->where('currency', 'USD')->update(['currency' => 'EGP']);
        DB::table('bank_accounts')->where('currency', 'USD')->update(['currency' => 'EGP']);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('base_currency', 3)->default('USD')->change();
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->change();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->change();
        });
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->change();
        });
    }
};
