<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.email', $user->email);
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function a_user_cannot_login_with_incorrect_credentials(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    #[Test]
    public function login_sets_a_persistent_remember_token_when_remember_is_true(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
            'remember' => true,
        ])->assertOk();

        $this->assertNotNull($user->fresh()->remember_token);
    }

    #[Test]
    public function login_is_rate_limited_per_email_and_ip(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertStatus(429);
    }

    #[Test]
    public function an_authenticated_user_can_logout(): void
    {
        // A real login-then-logout round trip is used here (rather than
        // actingAs()) because actingAs() injects the user directly into the
        // guard and bypasses the actual session/cookie lifecycle, which is
        // exactly what logout() needs to tear down.
        //
        // Assertions target the "web" guard explicitly: the "auth:sanctum"
        // middleware calls Auth::shouldUse('sanctum') as a side effect of
        // authenticating this request, which switches the *default* guard
        // for the rest of the process. Sanctum's guard then caches its
        // resolved user, so a default-guard check after logout would read
        // that stale cache instead of the (correctly logged out) "web" guard.
        $user = User::factory()->create(['password' => 'correct-password']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertOk();

        $this->assertAuthenticatedAs($user, 'web');

        $this->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertGuest('web');
    }

    #[Test]
    public function a_guest_cannot_logout(): void
    {
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    #[Test]
    public function login_from_an_untrusted_origin_fails_cleanly_instead_of_crashing(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $response = $this
            ->withHeader('Referer', 'http://not-a-trusted-origin.example.com')
            ->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'correct-password',
            ]);

        $response->assertStatus(400);
        $this->assertGuest('web');
    }

    #[Test]
    public function an_authenticated_user_can_fetch_their_own_user_record(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }
}
