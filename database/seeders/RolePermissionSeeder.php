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
        'clients.view',
        'clients.manage',
        'vendors.view',
        'vendors.manage',
        'invoices.view',
        'invoices.create',
        'invoices.manage',
        'expenses.view',
        'expenses.create',
        'expenses.manage',
        'cost_centers.view',
        'cost_centers.manage',
    ];

    /**
     * Transactions + accounting, no settings/user management — matches
     * the Accountant row of the module access matrix.
     *
     * @var array<int, string>
     */
    private const ACCOUNTANT_PERMISSIONS = [
        'accounts.view',
        'journals.view',
        'journals.create',
        'journals.post',
        'clients.view',
        'clients.manage',
        'vendors.view',
        'vendors.manage',
        'invoices.view',
        'invoices.create',
        'invoices.manage',
        'expenses.view',
        'expenses.create',
        'expenses.manage',
        'cost_centers.view',
        'cost_centers.manage',
    ];

    /**
     * Read-only Dashboard/P&L/Balance Sheet/Trial Balance — for now, the
     * read-only slice of what exists (Trial Balance, General Ledger, view
     * access to the transaction modules).
     *
     * @var array<int, string>
     */
    private const VIEWER_PERMISSIONS = [
        'accounts.view',
        'journals.view',
        'clients.view',
        'vendors.view',
        'invoices.view',
        'expenses.view',
        'cost_centers.view',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(self::PERMISSIONS);

        // Admin = all modules except Users (no users.* permissions exist
        // yet — the Users module lands later; this role already excludes
        // them by construction since it only syncs what's defined above).
        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin->syncPermissions(self::PERMISSIONS);

        $accountant = Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => 'web']);
        $accountant->syncPermissions(self::ACCOUNTANT_PERMISSIONS);

        $viewer = Role::firstOrCreate(['name' => 'Viewer', 'guard_name' => 'web']);
        $viewer->syncPermissions(self::VIEWER_PERMISSIONS);
    }
}
