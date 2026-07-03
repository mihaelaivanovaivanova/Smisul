<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_updates_profile_fields(): void
    {
        $user = User::factory()->create(['first_name' => 'Old']);

        $updated = (new ProfileService)->updateProfile($user, ['first_name' => 'New']);

        $this->assertSame('New', $updated->first_name);
    }

    #[Test]
    public function changing_email_revokes_verification_and_resends_it(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'old@example.com']);
        $this->assertNotNull($user->email_verified_at);

        $updated = (new ProfileService)->updateProfile($user, ['email' => 'new@example.com']);

        $this->assertSame('new@example.com', $updated->email);
        $this->assertNull($updated->email_verified_at);

        Notification::assertSentTo($updated, VerifyEmail::class);
    }

    #[Test]
    public function updating_email_to_the_same_value_does_not_revoke_verification(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'same@example.com']);

        $updated = (new ProfileService)->updateProfile($user, ['email' => 'same@example.com']);

        $this->assertNotNull($updated->email_verified_at);
        Notification::assertNothingSent();
    }

    #[Test]
    public function it_hashes_the_new_password(): void
    {
        $user = User::factory()->create();

        (new ProfileService)->updatePassword($user, 'brand-new-password');

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }
}
