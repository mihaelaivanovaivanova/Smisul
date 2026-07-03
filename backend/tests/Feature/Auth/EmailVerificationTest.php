<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_valid_signed_link_verifies_the_email(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->getJson($url)->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function verification_does_not_require_an_active_session(): void
    {
        // Deliberately no actingAs() — proves clicking the emailed link works
        // even on a different device/browser than the one used to register.
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->getJson($url)->assertOk();
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function an_invalid_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('not-the-real-email')],
        );

        $this->getJson($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function an_expired_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinutes(5),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->getJson($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function an_already_verified_user_hitting_the_link_again_gets_a_friendly_response(): void
    {
        $user = User::factory()->create(); // already verified by factory default

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->getJson($url)->assertOk()->assertJsonPath('message', 'Email address already verified.');
    }

    #[Test]
    public function an_authenticated_user_can_request_the_verification_email_be_resent(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertOk();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    #[Test]
    public function a_guest_cannot_request_a_resend(): void
    {
        $this->postJson('/api/v1/auth/email/verification-notification')->assertUnauthorized();
    }
}
