<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Avarewase\SsoClient\Contracts\ProvisionsAvarewaseUsers;
use Avarewase\SsoClient\DataObjects\AvarewaseTokens;
use Avarewase\SsoClient\DataObjects\AvarewaseUserInfo;
use Avarewase\SsoClient\Events\AvarewaseUserAuthenticated;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewAvarewaseUserDefaultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function userInfo(string $sub, string $email): AvarewaseUserInfo
    {
        return new AvarewaseUserInfo(
            sub: $sub,
            name: 'New Person',
            email: $email,
            emailVerified: true,
            picture: null,
        );
    }

    private function tokens(): AvarewaseTokens
    {
        return new AvarewaseTokens(accessToken: 'token', refreshToken: null, tokenType: 'Bearer', expiresIn: 3600);
    }

    public function test_a_first_time_avarewase_login_gets_viewer_role_and_suspended_status(): void
    {
        $userInfo = $this->userInfo('sub-1', 'new-person@example.com');

        /** @var User $user */
        $user = app(ProvisionsAvarewaseUsers::class)->resolve($userInfo);

        AvarewaseUserAuthenticated::dispatch($user, $userInfo, $this->tokens());

        $user->refresh();

        $this->assertSame('suspended', $user->status->value);
        $this->assertSame(['Viewer'], $user->getRoleNames()->all());
    }

    public function test_a_returning_users_role_and_status_are_left_untouched(): void
    {
        $tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co', 'base_currency' => 'EGP']);

        $existing = User::factory()->create([
            'tenant_id' => $tenant->id,
            'avarewase_sub' => 'sub-2',
            'email' => 'returning@example.com',
            'status' => 'active',
        ]);
        $existing->assignRole('Accountant');

        $userInfo = $this->userInfo('sub-2', 'returning@example.com');

        /** @var User $user */
        $user = app(ProvisionsAvarewaseUsers::class)->resolve($userInfo);

        AvarewaseUserAuthenticated::dispatch($user, $userInfo, $this->tokens());

        $user->refresh();

        $this->assertSame('active', $user->status->value);
        $this->assertSame(['Accountant'], $user->getRoleNames()->all());
    }
}
