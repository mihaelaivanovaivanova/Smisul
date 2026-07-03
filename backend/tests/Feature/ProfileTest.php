<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_guest_cannot_view_the_profile(): void
    {
        $this->getJson('/api/v1/profile')->assertUnauthorized();
    }

    #[Test]
    public function an_authenticated_user_can_view_their_own_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    #[Test]
    public function an_authenticated_user_can_update_their_profile(): void
    {
        $user = User::factory()->create(['first_name' => 'Old', 'phone' => null]);

        $this->actingAs($user)
            ->putJson('/api/v1/profile', [
                'first_name' => 'New',
                'phone' => '+359888000000',
                'newsletter_subscription' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'New')
            ->assertJsonPath('data.phone', '+359888000000')
            ->assertJsonPath('data.newsletter_subscription', true);
    }

    #[Test]
    public function changing_the_email_requires_re_verification(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'old@example.com']);
        $this->assertNotNull($user->email_verified_at);

        $this->actingAs($user)
            ->putJson('/api/v1/profile', ['email' => 'new@example.com'])
            ->assertOk()
            ->assertJsonPath('data.email', 'new@example.com');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function profile_update_rejects_an_email_already_taken_by_another_user(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'mine@example.com']);

        $this->actingAs($user)
            ->putJson('/api/v1/profile', ['email' => 'taken@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    #[Test]
    public function a_user_can_change_their_password_with_the_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => 'current-password']);

        $this->actingAs($user)
            ->putJson('/api/v1/profile/password', [
                'current_password' => 'current-password',
                'password' => 'Br4ndNewPassword',
                'password_confirmation' => 'Br4ndNewPassword',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('Br4ndNewPassword', $user->fresh()->password));
    }

    #[Test]
    public function password_change_fails_with_the_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => 'current-password']);

        $this->actingAs($user)
            ->putJson('/api/v1/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'Br4ndNewPassword',
                'password_confirmation' => 'Br4ndNewPassword',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('current-password', $user->fresh()->password));
    }

    #[Test]
    public function profile_response_never_exposes_the_password_hash(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    }
}
