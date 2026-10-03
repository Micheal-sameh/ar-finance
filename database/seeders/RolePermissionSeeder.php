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
        'purchase_orders.view',
        'purchase_orders.create',
        'purchase_orders.manage',
        'bills.view',
        'bills.create',
        'bills.manage',
        'fixed_assets.view',
        'fixed_assets.manage',
        'employees.view',
        'employees.manage',
        'payroll.view',
        'payroll.create',
        'payroll.manage',
        'bank_accounts.view',
        'bank_accounts.manage',
        'exchange_rates.view',
        'exchange_rates.manage',
        'users.view',
        'users.manage',
        'platform.access',
        'tenants.manage',
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
        'purchase_orders.view',
        'purchase_orders.create',
        'purchase_orders.manage',
        'bills.view',
        'bills.create',
        'bills.manage',
        'fixed_assets.view',
        'fixed_assets.manage',
        'employees.view',
        'employees.manage',
        'payroll.view',
        'payroll.create',
        'payroll.manage',
        'bank_accounts.view',
        'bank_accounts.manage',
        'exchange_rates.view',
        'exchange_rates.manage',
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
        'purchase_orders.view',
        'bills.view',
        'fixed_assets.view',
        'employees.view',
        'payroll.view',
        'bank_accounts.view',
        'exchange_rates.view',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Platform Admin = cross-tenant access (see TenantContext) — every
        // module permission plus the platform-only ones. Distinct from the
        // tenant-scoped Super Admin below, which is still confined to its
        // own tenant.
        $platformAdmin = Role::firstOrCreate(['name' => 'Platform Admin', 'guard_name' => 'web']);
        $platformAdmin->syncPermissions(self::PERMISSIONS);

        // Portal Manager = same cross-tenant permissions as Platform Admin
        // (including platform.access/tenants.manage), but the one carve-out
        // doesn't fit the permission system: it must never be able to grant
        // the Platform Admin role itself. That's enforced in
        // UpdateUserRequest/UserController, not here.
        $portalManager = Role::firstOrCreate(['name' => 'Portal Manager', 'guard_name' => 'web']);
        $portalManager->syncPermissions(self::PERMISSIONS);

        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(array_diff(self::PERMISSIONS, ['platform.access', 'tenants.manage']));

        // Admin = all modules except Users — user management (status,
        // role visibility) is reserved for Super Admin.
        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin->syncPermissions(array_diff(self::PERMISSIONS, ['users.view', 'users.manage', 'platform.access', 'tenants.manage']));

        $accountant = Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => 'web']);
        $accountant->syncPermissions(self::ACCOUNTANT_PERMISSIONS);

        $viewer = Role::firstOrCreate(['name' => 'Viewer', 'guard_name' => 'web']);
        $viewer->syncPermissions(self::VIEWER_PERMISSIONS);
    }
}
