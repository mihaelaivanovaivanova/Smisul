<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sends_a_reset_link_for_a_known_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    #[Test]
    public function it_responds_the_same_way_for_an_unknown_email_to_avoid_enumeration(): void
    {
        Notification::fake();

        $knownResponse = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => User::factory()->create()->email,
        ]);

        $unknownResponse = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        $knownResponse->assertOk();
        $unknownResponse->assertOk();
        $this->assertSame($knownResponse->json('message'), $unknownResponse->json('message'));
    }

    #[Test]
    public function the_reset_password_link_points_at_the_frontend(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $mail = $notification->toMail($user);
            $actionUrl = $mail->actionUrl;

            return str_starts_with($actionUrl, config('app.frontend_url').'/reset-password');
        });
    }

    #[Test]
    public function a_user_can_reset_their_password_with_a_valid_token(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Br4ndNewPassword',
            'password_confirmation' => 'Br4ndNewPassword',
        ])->assertOk();

        $this->assertTrue(Hash::check('Br4ndNewPassword', $user->fresh()->password));
    }

    #[Test]
    public function it_rejects_an_invalid_token(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'Br4ndNewPassword',
            'password_confirmation' => 'Br4ndNewPassword',
        ])->assertUnprocessable();

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }
}
