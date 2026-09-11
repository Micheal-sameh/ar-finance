<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co', 'base_currency' => 'EGP']);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_super_admin_can_view_the_users_list_with_roles_and_status(): void
    {
        $this->actingAsRole('Super Admin');

        $other = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Jane Accountant',
            'avarewase_membership_code' => 'MC-1234',
        ]);
        $other->assignRole('Accountant');

        $response = $this->get(route('users.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Users/Index')
            ->where('canManage', true)
            ->where('users.data.0.status', 'active')
            ->has('availableRoles')
        );

        $response->assertSee('Jane Accountant', false);
        $response->assertSee('MC-1234', false);
    }

    public function test_accountant_cannot_view_the_users_list(): void
    {
        $this->actingAsRole('Accountant');

        $this->get(route('users.index'))->assertForbidden();
    }

    public function test_super_admin_can_change_another_users_status_and_role(): void
    {
        $this->actingAsRole('Super Admin');

        $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $target->assignRole('Viewer');

        $this->put(route('users.update', $target), ['status' => 'suspended', 'role' => 'Accountant'])
            ->assertRedirect(route('users.index'));

        $target->refresh();
        $this->assertSame('suspended', $target->status->value);
        $this->assertSame(['Accountant'], $target->getRoleNames()->all());

        $this->put(route('users.update', $target), ['status' => 'active', 'role' => 'Viewer'])
            ->assertRedirect(route('users.index'));

        $target->refresh();
        $this->assertSame('active', $target->status->value);
        $this->assertSame(['Viewer'], $target->getRoleNames()->all());
    }

    public function test_a_user_cannot_edit_their_own_account(): void
    {
        $admin = $this->actingAsRole('Super Admin');

        $this->put(route('users.update', $admin), ['status' => 'suspended', 'role' => 'Viewer'])
            ->assertRedirect();

        $admin->refresh();
        $this->assertSame('active', $admin->status->value);
        $this->assertSame(['Super Admin'], $admin->getRoleNames()->all());
    }

    public function test_a_suspended_user_is_logged_out_on_their_next_request(): void
    {
        $user = $this->actingAsRole('Viewer');
        $user->update(['status' => 'suspended']);

        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
