<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCreationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co', 'base_currency' => 'EGP']);
    }

    private function actor(string $role, ?int $tenantId = null): User
    {
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Person',
            'email' => 'New.Person@Example.com',
            'phone' => '+20 100 555 0101',
            'password' => 'temporary-pass-1',
            'role' => 'Accountant',
            'tenant_ids' => [$this->tenant->id],
            'status' => 'active',
        ], $overrides);
    }

    public function test_platform_admin_creates_a_user_on_a_tenant(): void
    {
        $this->actingAs($this->actor('Platform Admin'))
            ->post(route('platform.users.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'new.person@example.com')->firstOrFail();

        $this->assertSame($this->tenant->id, $user->tenant_id);
        $this->assertSame('+201005550101', $user->phone);
        $this->assertSame(['Accountant'], $user->getRoleNames()->all());
        $this->assertFalse($user->hasConfirmedTwoFactor());
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $user->id, 'action' => 'created']);
    }

    public function test_portal_manager_can_create_users_too_but_not_platform_admins(): void
    {
        $manager = $this->actor('Portal Manager');

        $this->actingAs($manager)->post(route('platform.users.store'), $this->payload(['email' => 'a@example.com', 'phone' => '']))
            ->assertSessionHasNoErrors();

        $this->actingAs($manager)->post(route('platform.users.store'), $this->payload(['role' => 'Platform Admin', 'email' => 'b@example.com']))
            ->assertSessionHasErrors('role');
    }

    public function test_platform_roles_are_created_without_a_tenant(): void
    {
        $this->actingAs($this->actor('Platform Admin'))
            ->post(route('platform.users.store'), $this->payload(['role' => 'Portal Manager', 'tenant_ids' => [$this->tenant->id]]))
            ->assertSessionHasNoErrors();

        $this->assertNull(User::query()->where('email', 'new.person@example.com')->firstOrFail()->tenant_id);
    }

    public function test_a_tenant_role_requires_a_tenant(): void
    {
        $this->actingAs($this->actor('Platform Admin'))
            ->post(route('platform.users.store'), $this->payload(['tenant_ids' => []]))
            ->assertSessionHasErrors('tenant_ids');
    }

    public function test_email_or_phone_is_required_and_both_must_be_unique(): void
    {
        $admin = $this->actor('Platform Admin');
        User::factory()->create(['email' => 'taken@example.com', 'phone' => '+201005550101']);

        $this->actingAs($admin)->post(route('platform.users.store'), $this->payload(['email' => '', 'phone' => '']))
            ->assertSessionHasErrors(['email', 'phone']);

        $this->actingAs($admin)->post(route('platform.users.store'), $this->payload(['email' => 'taken@example.com']))
            ->assertSessionHasErrors('email');

        $this->actingAs($admin)->post(route('platform.users.store'), $this->payload(['email' => 'fresh@example.com', 'phone' => '0100 555 0101']))
            ->assertSessionHasNoErrors();
    }

    public function test_tenant_level_users_cannot_create_users(): void
    {
        $this->actingAs($this->actor('Super Admin', $this->tenant->id))
            ->post(route('platform.users.store'), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['name' => 'New Person']);
    }
}
