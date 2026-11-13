<?php

namespace Database\Seeders;

use App\DTOs\CreateCostCenterData;
use App\Enums\AccountType;
use App\Enums\GlMappingRole;
use App\Models\Account;
use App\Models\GlMapping;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Booking\DTOs\CreateBookableItemData;
use App\Modules\Booking\Models\BookableItem;
use App\Modules\Booking\Models\BookingType;
use App\Modules\Booking\Services\BookableItemService;
use App\Services\CostCenterService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds several tenants ("centers"), each with its own chart of accounts,
 * booking types and bookable items (each with its own profit center), plus login accounts for
 * every role. Booking Admin / Booking Agent users belong to all centers so
 * the multi-tenant booking view can be tested. All passwords: "password".
 */
class BookingDemoSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const CENTERS = [
        'cairo-sports-center' => 'Cairo Sports Center',
        'alex-sports-center' => 'Alexandria Sports Center',
        'giza-sports-center' => 'Giza Sports Center',
    ];

    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $tenants = [];

        foreach (self::CENTERS as $slug => $name) {
            $tenants[$slug] = $this->seedCenter($slug, $name);
        }

        $allTenantIds = collect($tenants)->pluck('id')->all();

        $this->user('booking-admin@avarewase.com', 'Booking Admin', 'Booking Admin', $tenants['cairo-sports-center'], $allTenantIds);
        $this->user('booking-agent@avarewase.com', 'Booking Agent', 'Booking Agent', $tenants['cairo-sports-center'], $allTenantIds);

        foreach ($tenants as $slug => $tenant) {
            $prefix = explode('-', $slug)[0];

            $this->user("admin.{$prefix}@avarewase.com", "{$tenant->name} Admin", 'Admin', $tenant);
            $this->user("accountant.{$prefix}@avarewase.com", "{$tenant->name} Accountant", 'Accountant', $tenant);
            $this->user("viewer.{$prefix}@avarewase.com", "{$tenant->name} Viewer", 'Viewer', $tenant);
        }
    }

    private function seedCenter(string $slug, string $name): Tenant
    {
        $tenant = Tenant::firstOrCreate(['slug' => $slug], ['name' => $name, 'base_currency' => 'EGP']);

        $this->callWith(ChartOfAccountsSeeder::class, ['tenantId' => $tenant->id]);

        $leaf = fn (string $code, string $label, AccountType $type, string $parentCode): Account => Account::updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => $code],
            [
                'name' => $label,
                'type' => $type,
                'normal_balance' => $type->defaultNormalBalance(),
                'parent_id' => Account::where('tenant_id', $tenant->id)->where('code', $parentCode)->value('id'),
                'is_active' => true,
                'is_deletable' => false,
            ],
        );

        $revenue = $leaf('4100', 'Booking Revenue', AccountType::Revenue, '4000');
        $cash = $leaf('1211', 'Cash on Hand', AccountType::Asset, '1200');
        $bank = $leaf('1212', 'Bank', AccountType::Asset, '1200');
        $pos = $leaf('1213', 'POS Card Clearing', AccountType::Asset, '1200');

        foreach ([GlMappingRole::BookingRevenue->value => $revenue, GlMappingRole::BookingCashPayment->value => $cash, GlMappingRole::BookingBankPayment->value => $bank, GlMappingRole::BookingPosPayment->value => $pos] as $role => $account) {
            GlMapping::withoutGlobalScopes()->updateOrCreate(['tenant_id' => $tenant->id, 'role' => $role], ['account_id' => $account->id]);
        }

        // Services stamp tenant_id from the active context / logged-in user.
        $admin = User::updateOrCreate(
            ['email' => "seeder.{$slug}@avarewase.com"],
            ['name' => 'Seeder', 'tenant_id' => $tenant->id, 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );
        $admin->syncRoles(['Super Admin']);
        Auth::login($admin);

        $training = BookingType::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Training']);
        $match = BookingType::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Match']);

        $items = app(BookableItemService::class);

        foreach (['Court 1' => 200, 'Court 2' => 250, 'Main Hall' => 1500] as $itemName => $price) {
            if (BookableItem::where('tenant_id', $tenant->id)->where('name', $itemName)->exists()) {
                continue;
            }

            // Each item is its own profit center so revenue is reported per item.
            $costCenter = app(CostCenterService::class)->create(CreateCostCenterData::fromArray([
                'name' => $itemName,
                'type' => 'profit',
            ]));

            $items->create(CreateBookableItemData::fromArray([
                'name' => $itemName,
                'description' => "{$itemName} at {$name}",
                'is_exclusive' => true,
                'account_id' => $revenue->id,
                'cost_center_id' => $costCenter->id,
                'booking_types' => [
                    ['booking_type_id' => $training->id, 'rate_type' => 'hourly', 'price' => $price],
                    ['booking_type_id' => $match->id, 'rate_type' => 'flat', 'price' => $price * 3],
                ],
            ]));
        }

        Auth::logout();
        $admin->delete();

        return $tenant;
    }

    /**
     * @param  array<int, int>|null  $tenantIds
     */
    private function user(string $email, string $name, string $role, Tenant $tenant, ?array $tenantIds = null): void
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'tenant_id' => $tenant->id, 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );

        $user->syncRoles([$role]);
        $user->tenants()->syncWithoutDetaching($tenantIds ?? [$tenant->id]);
    }
}
