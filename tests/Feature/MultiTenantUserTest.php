<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\NormalBalance;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenantUserTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->alpha = Tenant::create(['name' => 'Alpha Co', 'slug' => 'alpha-co', 'base_currency' => 'EGP']);
        $this->beta = Tenant::create(['name' => 'Beta Co', 'slug' => 'beta-co', 'base_currency' => 'EGP']);
    }

    private function accountant(): User
    {
        $user = User::factory()->create(['tenant_id' => $this->alpha->id]);
        $user->tenants()->attach($this->beta->id);
        $user->assignRole('Accountant');

        return $user;
    }

    private function account(Tenant $tenant, string $name): Account
    {
        return Account::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'code' => '1000',
            'name' => $name,
            'type' => AccountType::Asset,
            'normal_balance' => NormalBalance::Debit,
        ]);
    }

    public function test_a_users_default_tenant_is_always_one_of_their_tenants(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->alpha->id]);

        $this->assertTrue($user->canAccessTenant($this->alpha->id));
        $this->assertFalse($user->canAccessTenant($this->beta->id));
    }

    public function test_user_works_in_their_default_tenant_until_they_switch(): void
    {
        $user = $this->accountant();
        $this->account($this->alpha, 'Alpha Cash');
        $this->account($this->beta, 'Beta Cash');

        $this->actingAs($user)->get(route('accounts.index'))
            ->assertInertia(fn ($page) => $page->has('accounts', 1)->where('accounts.0.name', 'Alpha Cash'));

        $this->post(route('tenant.switch', $this->beta))->assertRedirect(route('dashboard'));

        $this->get(route('accounts.index'))
            ->assertInertia(fn ($page) => $page->has('accounts', 1)->where('accounts.0.name', 'Beta Cash'));
    }

    public function test_switcher_is_shared_only_for_users_with_several_tenants(): void
    {
        $multi = $this->accountant();
        $single = User::factory()->create(['tenant_id' => $this->alpha->id]);

        $this->actingAs($multi)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->has('memberTenants.tenants', 2)->where('memberTenants.activeTenantId', $this->alpha->id));

        $this->actingAs($single)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('memberTenants', null));
    }

    public function test_user_cannot_switch_into_a_tenant_they_do_not_belong_to(): void
    {
        $other = Tenant::create(['name' => 'Gamma Co', 'slug' => 'gamma-co', 'base_currency' => 'EGP']);

        $this->actingAs($this->accountant())->post(route('tenant.switch', $other))->assertForbidden();
    }

    public function test_user_cannot_switch_into_an_inactive_tenant(): void
    {
        $this->beta->update(['is_active' => false]);

        $this->actingAs($this->accountant())->post(route('tenant.switch', $this->beta))->assertForbidden();
    }

    public function test_deactivating_the_active_tenant_moves_the_user_to_another_one(): void
    {
        $user = $this->accountant();

        $this->actingAs($user)->post(route('tenant.switch', $this->beta));
        $this->beta->update(['is_active' => false]);

        $this->get(route('dashboard'))->assertOk();
        $this->assertSame($this->alpha->id, session('active_tenant_id'));
    }

    public function test_user_is_logged_out_only_when_every_tenant_is_deactivated(): void
    {
        $user = $this->accountant();
        $this->alpha->update(['is_active' => false]);
        $this->beta->update(['is_active' => false]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_platform_admin_assigns_several_tenants_and_the_default_survives(): void
    {
        $admin = User::factory()->create(['tenant_id' => null]);
        $admin->assignRole('Platform Admin');
        $user = User::factory()->create(['tenant_id' => $this->alpha->id]);

        $this->actingAs($admin)
            ->put(route('platform.users.assign-tenant', $user), ['tenant_ids' => [$this->beta->id, $this->alpha->id]])
            ->assertRedirect(route('users.index'));

        $this->assertEqualsCanonicalizing([$this->alpha->id, $this->beta->id], $user->tenants()->pluck('tenants.id')->all());
        $this->assertSame($this->alpha->id, $user->fresh()->tenant_id);

        $this->put(route('platform.users.assign-tenant', $user), ['tenant_ids' => [$this->beta->id]]);

        $this->assertSame([$this->beta->id], $user->tenants()->pluck('tenants.id')->all());
        $this->assertSame($this->beta->id, $user->fresh()->tenant_id);
    }

    public function test_creating_a_user_with_several_tenants(): void
    {
        $admin = User::factory()->create(['tenant_id' => null]);
        $admin->assignRole('Platform Admin');

        $this->actingAs($admin)->post(route('platform.users.store'), [
            'name' => 'Multi Person',
            'email' => 'multi@example.com',
            'password' => 'temporary-pass-1',
            'role' => 'Accountant',
            'status' => 'active',
            'tenant_ids' => [$this->beta->id, $this->alpha->id],
        ])->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'multi@example.com')->firstOrFail();

        $this->assertSame($this->beta->id, $user->tenant_id);
        $this->assertCount(2, $user->tenants);
    }

    public function test_a_tenant_admin_sees_and_manages_users_who_belong_to_the_tenant_but_not_by_default(): void
    {
        $admin = User::factory()->create(['tenant_id' => $this->beta->id]);
        $admin->assignRole('Super Admin');
        $guest = $this->accountant(); // default Alpha, also member of Beta

        $this->actingAs($admin)->get(route('users.index'))
            ->assertInertia(fn ($page) => $page->where('users.data', fn ($rows) => collect($rows)->pluck('id')->contains($guest->id)));

        $this->put(route('users.update', $guest), ['status' => 'suspended', 'role' => 'Accountant'])
            ->assertRedirect(route('users.index'));
    }
}
