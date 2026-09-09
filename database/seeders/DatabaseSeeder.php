<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'avarewase-demo'],
            ['name' => 'Avarewase Demo Co.', 'base_currency' => 'USD'],
        );

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $user->assignRole('Super Admin');

        // Dev-login account (see DevLoginController) — recreated on every
        // seed/migrate:fresh so it survives database resets during local
        // development instead of silently disappearing.
        $devUser = User::updateOrCreate(
            ['email' => 'micheal.sameh@avarewase.com'],
            [
                'name' => 'Micheal Sameh',
                'tenant_id' => $tenant->id,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $devUser->syncRoles(['Super Admin']);
    }
}
