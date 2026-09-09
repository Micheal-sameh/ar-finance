<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * There's no public page at "/" — it's the (protected) dashboard. An
     * unauthenticated visitor should be sent to "Login with Avarewase"
     * rather than hitting Laravel's default (undefined) login route.
     */
    public function test_guests_are_redirected_to_avarewase_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('avarewase.login'));
    }
}
