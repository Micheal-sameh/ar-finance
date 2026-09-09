<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the module → permission → role matrix. As new modules land, add
 * their `<module>.*` permissions here and slot them into the right roles
 * rather than checking role names directly anywhere in the app.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    public const PERMISSIONS = [
        'accounts.view',
        'accounts.create',
        'accounts.manage',
        'accounts.delete',
        'journals.view',
        'journals.create',
        'journals.post',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(self::PERMISSIONS);

        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin->syncPermissions(self::PERMISSIONS);

        $accountant = Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => 'web']);
        $accountant->syncPermissions([
            'accounts.view',
            'journals.view',
            'journals.create',
            'journals.post',
        ]);

        $viewer = Role::firstOrCreate(['name' => 'Viewer', 'guard_name' => 'web']);
        $viewer->syncPermissions([
            'accounts.view',
            'journals.view',
        ]);
    }
}
