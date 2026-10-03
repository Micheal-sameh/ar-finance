<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function platformAdmin(): User
    {
        $user = User::factory()->create(['tenant_id' => null]);
        $user->assignRole('Platform Admin');

        return $user;
    }

    public function test_platform_admin_can_list_and_create_tenants_with_the_standard_chart_of_accounts(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get(route('platform.tenants.index'))
            ->assertInertia(fn ($page) => $page->component('Platform/Tenants/Index'));

        $this->actingAs($admin)->post(route('platform.tenants.store'), [
            'name' => 'New Co',
            'slug' => 'new-co',
            'base_currency' => 'USD',
        ])->assertRedirect(route('platform.tenants.index'));

        $tenant = Tenant::where('slug', 'new-co')->firstOrFail();
        $this->assertDatabaseHas('accounts', ['tenant_id' => $tenant->id, 'code' => '1000', 'name' => 'Assets']);
        $this->assertGreaterThan(1, Account::where('tenant_id', $tenant->id)->count());
    }

    public function test_platform_admin_can_switch_into_a_tenant_and_create_data_scoped_to_it(): void
    {
        $admin = $this->platformAdmin();
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'base_currency' => 'EGP']);

        $this->actingAs($admin)->post(route('platform.switch-tenant', $tenant))
            ->assertRedirect(route('dashboard'));

        $response = $this->actingAs($admin)->post(route('accounts.store'), [
            'code' => '1000',
            'name' => 'Cash',
            'type' => AccountType::Asset->value,
        ]);

        $response->assertRedirect(route('accounts.index'));
        $this->assertDatabaseHas('accounts', ['tenant_id' => $tenant->id, 'code' => '1000', 'name' => 'Cash']);
    }

    public function test_platform_admin_without_an_active_tenant_is_redirected_away_from_ordinary_routes(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertRedirect(route('platform.dashboard'));
    }

    public function test_ordinary_user_cannot_access_the_platform_area(): void
    {
        $tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co', 'base_currency' => 'EGP']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('Super Admin');

        $this->actingAs($user)->get(route('platform.dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('platform.tenants.index'))->assertForbidden();
    }

    public function test_platform_admin_sees_combined_accounts_and_journals_without_impersonating(): void
    {
        $admin = $this->platformAdmin();

        $tenantA = Tenant::create(['name' => 'Alpha Co', 'slug' => 'alpha-co', 'base_currency' => 'EGP']);
        $tenantB = Tenant::create(['name' => 'Beta Co', 'slug' => 'beta-co', 'base_currency' => 'EGP']);
        (new ChartOfAccountsSeeder)->run($tenantA->id);
        (new ChartOfAccountsSeeder)->run($tenantB->id);

        $response = $this->actingAs($admin)->get(route('accounts.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/Accounts/Index')
            ->where('viewingAllTenants', true)
        );
        $response->assertSee('Alpha Co', false);
        $response->assertSee('Beta Co', false);

        $accountIds = Account::where('tenant_id', $tenantA->id)->pluck('id');
        $this->assertGreaterThan(0, $accountIds->count());
    }

    public function test_platform_admin_without_an_active_tenant_cannot_create_tenant_scoped_data(): void
    {
        $admin = $this->platformAdmin();

        $response = $this->actingAs($admin)->post(route('accounts.store'), [
            'code' => '1000',
            'name' => 'Cash',
            'type' => AccountType::Asset->value,
        ]);

        $response->assertRedirect(route('platform.dashboard'));
        $this->assertDatabaseMissing('accounts', ['code' => '1000', 'name' => 'Cash']);
    }

    public function test_platform_admin_sees_combined_users_exchange_rates_and_general_ledger_without_impersonating(): void
    {
        $admin = $this->platformAdmin();

        $tenant = Tenant::create(['name' => 'Alpha Co', 'slug' => 'alpha-co', 'base_currency' => 'EGP']);
        (new ChartOfAccountsSeeder)->run($tenant->id);
        $tenantUser = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Tenant Person']);
        $tenantUser->assignRole('Viewer');

        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Users/Index')
                ->where('viewingAllTenants', true)
                ->where('canManage', false)
            )
            ->assertSee('Tenant Person', false)
            ->assertSee('Alpha Co', false);

        $this->actingAs($admin)->get(route('exchange-rates.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tools/ExchangeRates/Index')
                ->where('viewingAllTenants', true)
                ->where('canManage', false)
            );

        $this->actingAs($admin)->get(route('reports.general-ledger'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Accounting/Reports/GeneralLedger'));
    }
}
