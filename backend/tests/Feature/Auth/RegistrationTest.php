<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'phone' => '+359888123456',
            'password' => 'Sup3rSecret1',
            'password_confirmation' => 'Sup3rSecret1',
            'newsletter_subscription' => true,
            'marketing_consent' => false,
            'gdpr_consent' => true,
        ], $overrides);
    }

    #[Test]
    public function a_guest_can_register_with_valid_data(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->validPayload());

        $response->assertCreated();
        $response->assertJsonPath('data.email', 'ada@example.com');
        $response->assertJsonPath('data.role', 'customer');
        $response->assertJsonMissingPath('data.password');

        $user = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertSame(Role::Customer, $user->role);
        $this->assertNotNull($user->gdpr_consent_at);
        $this->assertTrue($user->newsletter_subscription);
        $this->assertFalse($user->marketing_consent);
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    #[Test]
    public function registration_ignores_a_client_supplied_role(): void
    {
        $this->postJson('/api/v1/auth/register', $this->validPayload(['role' => 'administrator']))
            ->assertCreated()
            ->assertJsonPath('data.role', 'customer');
    }

    #[Test]
    public function it_requires_gdpr_consent(): void
    {
        $this->postJson('/api/v1/auth/register', $this->validPayload(['gdpr_consent' => false]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('gdpr_consent');
    }

    #[Test]
    public function it_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->postJson('/api/v1/auth/register', $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    #[Test]
    public function it_rejects_a_weak_password(): void
    {
        $this->postJson('/api/v1/auth/register', $this->validPayload([
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]))->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    #[Test]
    public function it_rejects_a_mismatched_password_confirmation(): void
    {
        $this->postJson('/api/v1/auth/register', $this->validPayload([
            'password_confirmation' => 'SomethingElse1',
        ]))->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    #[Test]
    public function it_requires_first_name_last_name_and_email(): void
    {
        $this->postJson('/api/v1/auth/register', $this->validPayload([
            'first_name' => '',
            'last_name' => '',
            'email' => '',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['first_name', 'last_name', 'email']);
    }

    #[Test]
    public function it_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/register', $this->validPayload(['email' => "user{$i}@example.com"]));
        }

        $this->postJson('/api/v1/auth/register', $this->validPayload(['email' => 'oneTooMany@example.com']))
            ->assertStatus(429);
    }
}
