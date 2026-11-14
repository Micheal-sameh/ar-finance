<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\GlMappingRole;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role, ?Tenant $tenant = null): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant?->id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_a_platform_admin_switches_a_module_on_and_off_for_a_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Church', 'slug' => 'church', 'base_currency' => 'EGP']);
        $admin = $this->userWithRole('Platform Admin');
        $payload = ['name' => 'Church', 'slug' => 'church', 'base_currency' => 'EGP', 'is_active' => true];

        $this->actingAs($admin)->put(route('platform.tenants.update', $tenant), $payload + ['modules' => ['donations']])
            ->assertSessionHasNoErrors();
        $this->assertTrue($tenant->fresh()->modules === ['donations']);

        $this->actingAs($admin)->put(route('platform.tenants.update', $tenant), $payload + ['modules' => []])
            ->assertSessionHasNoErrors();
        $this->assertSame([], $tenant->fresh()->modules);
    }

    public function test_a_portal_manager_can_switch_modules_too_and_a_tenant_admin_cannot(): void
    {
        $tenant = Tenant::create(['name' => 'Church', 'slug' => 'church', 'base_currency' => 'EGP']);
        $payload = ['name' => 'Church', 'slug' => 'church', 'base_currency' => 'EGP', 'modules' => ['donations']];

        $this->actingAs($this->userWithRole('Portal Manager'))->put(route('platform.tenants.update', $tenant), $payload)
            ->assertSessionHasNoErrors();
        $this->assertSame(['donations'], $tenant->fresh()->modules);

        $tenant->update(['modules' => []]);

        $this->actingAs($this->userWithRole('Super Admin', $tenant))->put(route('platform.tenants.update', $tenant), $payload)
            ->assertForbidden();
        $this->assertSame([], $tenant->fresh()->modules);
    }

    public function test_a_module_can_be_toggled_for_a_tenant_straight_from_the_list(): void
    {
        $tenant = Tenant::create(['name' => 'Church', 'slug' => 'church', 'base_currency' => 'EGP']);
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'base_currency' => 'EGP']);
        $admin = $this->userWithRole('Platform Admin');

        $this->actingAs($admin)->put(route('platform.tenants.modules', $tenant), ['modules' => ['donations']])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
        $this->assertSame(['donations'], $tenant->fresh()->modules);
        $this->assertNull($other->fresh()->modules);

        $this->actingAs($admin)->put(route('platform.tenants.modules', $tenant), ['modules' => []])->assertSessionHasNoErrors();
        $this->assertSame([], $tenant->fresh()->modules);
    }

    public function test_toggling_a_module_from_the_list_rejects_unknown_modules_and_non_managers(): void
    {
        $tenant = Tenant::create(['name' => 'Church', 'slug' => 'church', 'base_currency' => 'EGP']);

        $this->actingAs($this->userWithRole('Platform Admin'))->put(route('platform.tenants.modules', $tenant), ['modules' => ['spaceships']])
            ->assertSessionHasErrors('modules.0');
        $this->actingAs($this->userWithRole('Platform Admin'))->put(route('platform.tenants.modules', $tenant), [])
            ->assertSessionHasErrors('modules');

        $this->actingAs($this->userWithRole('Super Admin', $tenant))->put(route('platform.tenants.modules', $tenant), ['modules' => ['donations']])
            ->assertForbidden();
        $this->assertNull($tenant->fresh()->modules);
    }

    public function test_an_unknown_module_is_rejected(): void
    {
        $tenant = Tenant::create(['name' => 'Church', 'slug' => 'church', 'base_currency' => 'EGP']);

        $this->actingAs($this->userWithRole('Platform Admin'))
            ->put(route('platform.tenants.update', $tenant), ['name' => 'Church', 'slug' => 'church', 'base_currency' => 'EGP', 'modules' => ['spaceships']])
            ->assertSessionHasErrors('modules.0');
    }

    public function test_a_new_tenant_can_be_created_with_modules(): void
    {
        $this->actingAs($this->userWithRole('Platform Admin'))->post(route('platform.tenants.store'), [
            'name' => 'New Church',
            'slug' => 'new-church',
            'base_currency' => 'EGP',
            'modules' => ['donations'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['donations'], Tenant::where('slug', 'new-church')->firstOrFail()->modules);
    }

    public function test_the_module_is_shared_with_the_frontend_and_hidden_where_it_is_off(): void
    {
        $on = Tenant::create(['name' => 'On', 'slug' => 'on', 'base_currency' => 'EGP', 'modules' => ['donations']]);
        $off = Tenant::create(['name' => 'Off', 'slug' => 'off', 'base_currency' => 'EGP']);

        $this->actingAs($this->userWithRole('Admin', $on))->get(route('donations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('enabledModules', ['donations']));

        $this->actingAs($this->userWithRole('Admin', $off))->get(route('donations.index'))->assertNotFound();
        $this->actingAs($this->userWithRole('Admin', $off))->get(route('donors.index'))->assertNotFound();
    }

    public function test_donation_roles_only_appear_in_the_gl_mapping_for_tenants_with_the_module(): void
    {
        $on = Tenant::create(['name' => 'On', 'slug' => 'on', 'base_currency' => 'EGP', 'modules' => ['donations']]);
        $off = Tenant::create(['name' => 'Off', 'slug' => 'off', 'base_currency' => 'EGP']);

        $roles = fn (Tenant $tenant) => collect(
            $this->actingAs($this->userWithRole('Admin', $tenant))->get(route('gl-mapping.index'))->assertOk()->viewData('page')['props']['rows']
        )->pluck('role');

        $this->assertTrue($roles($on)->contains(GlMappingRole::DonationRevenue->value));
        $this->assertTrue($roles($on)->contains(GlMappingRole::DonationPosPayment->value));
        $this->assertFalse($roles($off)->contains(GlMappingRole::DonationRevenue->value));
        $this->assertTrue($roles($off)->contains(GlMappingRole::BookingRevenue->value));
    }

    public function test_a_donation_account_must_be_an_asset_or_income_account_as_the_role_requires(): void
    {
        $tenant = Tenant::create(['name' => 'On', 'slug' => 'on', 'base_currency' => 'EGP', 'modules' => ['donations']]);
        $expense = Account::create(['tenant_id' => $tenant->id, 'code' => '5000', 'name' => 'Rent', 'type' => AccountType::Expense, 'normal_balance' => 'debit']);

        $this->actingAs($this->userWithRole('Admin', $tenant))
            ->put(route('gl-mapping.update'), ['mappings' => [GlMappingRole::DonationCashPayment->value => $expense->id]])
            ->assertSessionHas('error');
    }
}
