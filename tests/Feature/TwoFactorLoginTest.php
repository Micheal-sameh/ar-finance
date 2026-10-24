<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorLoginTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        $tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co', 'base_currency' => 'EGP']);

        return User::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'email' => 'person@example.com',
            'phone' => '+20 100 123 4567',
        ], $attributes));
    }

    private function enrolled(User $user): string
    {
        $secret = (new Totp)->generateSecret();
        $user->forceFill(['two_factor_secret' => $secret])->save();
        $user->confirmTwoFactor();

        return $secret;
    }

    public function test_password_alone_does_not_log_in_by_email(): void
    {
        $this->user();

        $this->post(route('login.store'), ['identifier' => 'person@example.com', 'password' => 'password'])
            ->assertRedirect(route('two-factor.show'));

        $this->assertGuest();
    }

    public function test_phone_number_works_in_any_format(): void
    {
        $user = $this->user();

        $this->post(route('login.store'), ['identifier' => '+201001234567', 'password' => 'password'])
            ->assertRedirect(route('two-factor.show'));

        $this->assertSame($user->id, session('two_factor.user_id'));
    }

    public function test_wrong_password_and_unknown_user_get_the_same_error(): void
    {
        $this->user();

        $this->post(route('login.store'), ['identifier' => 'person@example.com', 'password' => 'nope'])
            ->assertSessionHasErrors('identifier');
        $this->post(route('login.store'), ['identifier' => 'ghost@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('identifier');
        $this->assertNull(session('two_factor.user_id'));
    }

    public function test_suspended_user_cannot_start_a_login(): void
    {
        $this->user(['status' => 'suspended']);

        $this->post(route('login.store'), ['identifier' => 'person@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('identifier');
    }

    public function test_user_without_mfa_must_enrol_then_gets_recovery_codes(): void
    {
        $user = $this->user();
        $this->post(route('login.store'), ['identifier' => 'person@example.com', 'password' => 'password']);

        $this->get(route('two-factor.show'))
            ->assertInertia(fn ($page) => $page->component('Auth/TwoFactorSetup')->has('secret'));

        $secret = $user->fresh()->two_factor_secret;

        $this->post(route('two-factor.store'), ['code' => (new Totp)->codeAt($secret, time())])
            ->assertRedirect(route('two-factor.recovery-codes'));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->fresh()->hasConfirmedTwoFactor());
        $this->assertCount(8, $user->fresh()->two_factor_recovery_codes);
    }

    public function test_enrolment_with_a_bad_code_does_not_log_in(): void
    {
        $user = $this->user();
        $this->post(route('login.store'), ['identifier' => 'person@example.com', 'password' => 'password']);
        $this->get(route('two-factor.show'));

        $this->post(route('two-factor.store'), ['code' => '000000'])->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertFalse($user->fresh()->hasConfirmedTwoFactor());
    }

    public function test_enrolled_user_logs_in_with_an_authenticator_code(): void
    {
        $user = $this->user();
        $secret = $this->enrolled($user);

        $this->post(route('login.store'), ['identifier' => 'person@example.com', 'password' => 'password']);
        $this->get(route('two-factor.show'))->assertInertia(fn ($page) => $page->component('Auth/TwoFactorChallenge'));

        $this->post(route('two-factor.store'), ['code' => (new Totp)->codeAt($secret, time())])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_used_code_cannot_be_replayed(): void
    {
        $user = $this->user();
        $code = (new Totp)->codeAt($this->enrolled($user), time());

        $this->post(route('login.store'), ['identifier' => 'person@example.com', 'password' => 'password']);
        $this->post(route('two-factor.store'), ['code' => $code]);
        $this->post(route('logout'));

        $this->post(route('login.store'), ['identifier' => 'person@example.com', 'password' => 'password']);
        $this->post(route('two-factor.store'), ['code' => $code])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_a_recovery_code_works_once(): void
    {
        $user = $this->user();
        $this->enrolled($user);
        $recovery = $user->fresh()->two_factor_recovery_codes[0];

        $this->post(route('login.store'), ['identifier' => 'person@example.com', 'password' => 'password']);
        $this->post(route('two-factor.store'), ['code' => $recovery])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotContains($recovery, $user->fresh()->two_factor_recovery_codes);
    }

    public function test_too_many_wrong_codes_abandons_the_pending_login(): void
    {
        $this->enrolled($this->user());
        $this->post(route('login.store'), ['identifier' => 'person@example.com', 'password' => 'password']);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('two-factor.store'), ['code' => '000000'])->assertSessionHasErrors('code');
        }

        $this->post(route('two-factor.store'), ['code' => '000000'])->assertRedirect(route('login'));
        $this->assertNull(session('two_factor.user_id'));
    }

    public function test_two_factor_page_without_a_pending_login_goes_to_login(): void
    {
        $this->get(route('two-factor.show'))->assertRedirect(route('login'));
    }

    public function test_two_factor_secrets_are_not_serialised(): void
    {
        $user = $this->user();
        $this->enrolled($user);

        $this->assertArrayNotHasKey('two_factor_secret', $user->fresh()->toArray());
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $user->fresh()->toArray());
    }
}
