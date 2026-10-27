<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_renders(): void
    {
        $this->get(route('password.request'))
            ->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_a_reset_link_is_sent_to_a_known_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'person@example.com']);

        $this->post(route('password.email'), ['email' => 'person@example.com'])->assertSessionHas('success');

        Notification::assertSentTo($user, QueuedResetPassword::class);
    }

    public function test_the_reset_link_email_is_queued_instead_of_sent_during_the_request(): void
    {
        Queue::fake();
        $user = User::factory()->create(['email' => 'person@example.com']);

        $this->post(route('password.email'), ['email' => 'person@example.com'])->assertSessionHas('success');

        $this->assertInstanceOf(ShouldQueue::class, new QueuedResetPassword('token'));
        Queue::assertPushed(SendQueuedNotifications::class, fn ($job) => $job->notification instanceof QueuedResetPassword
            && $job->notifiables->contains($user));
    }

    public function test_an_unknown_email_gets_the_same_response_and_no_mail(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'ghost@example.com'])->assertSessionHas('success');

        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_a_valid_token_without_logging_in(): void
    {
        $user = User::factory()->create(['email' => 'person@example.com']);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'person@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->post(route('login.store'), ['identifier' => 'person@example.com', 'password' => 'new-password-123'])
            ->assertSessionHasNoErrors();
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'person@example.com']);

        $this->post(route('password.update'), [
            'token' => 'bogus',
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('email');
    }

    public function test_local_environment_logs_in_without_mfa(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->app['env'] = 'local';
        $user = User::factory()->create(['email' => 'person@example.com']);

        $this->post(route('login.store'), ['identifier' => 'person@example.com', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }
}
