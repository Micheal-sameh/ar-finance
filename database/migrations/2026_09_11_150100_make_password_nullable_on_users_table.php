<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DefaultAvarewaseUserProvisioner (avarewase/sso-client) never sets a
 * password on SSO-provisioned users — this app is SSO-first, and
 * `password` is only ever populated for the local dev-login fallback
 * (see DevLoginController). The column was left NOT NULL from the
 * framework's default users migration, which broke every first-time
 * SSO login with a NOT NULL constraint violation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
