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

    public function test_super_admin_can_edit_a_users_name_email_and_phone(): void
    {
        $this->actingAsRole('Super Admin');

        $target = User::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'old@example.com']);
        $target->assignRole('Viewer');

        $this->put(route('users.update-details', $target), [
            'name' => 'New Name',
            'email' => ' New@Example.com ',
            'phone' => '+20 100 123 4567',
        ])->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame('New Name', $target->name);
        $this->assertSame('new@example.com', $target->email);
        $this->assertSame('+201001234567', $target->phone);
        $this->assertSame(['Viewer'], $target->getRoleNames()->all());
    }

    public function test_email_and_phone_must_stay_unique_and_one_of_them_is_required(): void
    {
        $this->actingAsRole('Super Admin');

        User::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'taken@example.com', 'phone' => '+201111111111']);
        $target = User::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'mine@example.com']);
        $target->assignRole('Viewer');

        $this->put(route('users.update-details', $target), ['name' => 'X', 'email' => 'taken@example.com'])
            ->assertSessionHasErrors('email');
        $this->put(route('users.update-details', $target), ['name' => 'X', 'phone' => '+20 111 111 1111'])
            ->assertSessionHasErrors('phone');
        $this->put(route('users.update-details', $target), ['name' => 'X'])
            ->assertSessionHasErrors(['email', 'phone']);

        // Keeping their own email is not a clash.
        $this->put(route('users.update-details', $target), ['name' => 'Renamed', 'email' => 'mine@example.com'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $target->refresh()->name);
    }

    public function test_only_users_with_manage_permission_can_edit_details_and_not_across_services(): void
    {
        $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $target->assignRole('Viewer');

        $this->actingAsRole('Accountant');
        $this->put(route('users.update-details', $target), ['name' => 'Nope', 'email' => 'nope@example.com'])->assertForbidden();

        $other = Tenant::create(['name' => 'Other Co', 'slug' => 'other-co', 'base_currency' => 'EGP']);
        $outsider = User::factory()->create(['tenant_id' => $other->id]);
        $outsider->assignRole('Viewer');

        $this->actingAsRole('Super Admin');
        $this->put(route('users.update-details', $outsider), ['name' => 'Nope', 'email' => 'nope@example.com'])->assertForbidden();

        $this->assertNotSame('Nope', $target->refresh()->name);
        $this->assertNotSame('Nope', $outsider->refresh()->name);
    }

    public function test_platform_admin_can_edit_details_from_the_all_services_view(): void
    {
        $admin = User::factory()->create(['tenant_id' => null]);
        $admin->assignRole('Platform Admin');
        $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $target->assignRole('Viewer');

        $this->actingAs($admin)
            ->put(route('platform.users.update-details', $target), ['name' => 'By Platform', 'email' => 'platform@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame('By Platform', $target->refresh()->name);
    }
}
