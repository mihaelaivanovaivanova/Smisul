<?php

namespace Tests\Feature;

use App\Enums\ConsentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConsentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_guest_can_read_their_cookie_preferences_by_identifier(): void
    {
        $response = $this->getJson('/api/v1/consent/cookies?guest_identifier=11111111-1111-1111-1111-111111111111');

        $response->assertOk();
        $response->assertJson(['data' => [
            'necessary' => true,
            'analytics' => false,
            'marketing' => false,
            'preferences' => false,
        ]]);
    }

    #[Test]
    public function a_guest_can_store_cookie_preferences(): void
    {
        $guestIdentifier = '22222222-2222-2222-2222-222222222222';

        $response = $this->postJson('/api/v1/consent/cookies', [
            'guest_identifier' => $guestIdentifier,
            'categories' => ['analytics' => true, 'marketing' => false, 'preferences' => true],
        ]);

        $response->assertCreated();
        $response->assertJson(['data' => [
            'necessary' => true,
            'analytics' => true,
            'marketing' => false,
            'preferences' => true,
        ]]);

        $this->assertDatabaseHas('consents', [
            'guest_identifier' => $guestIdentifier,
            'type' => ConsentType::CookieAnalytics->value,
            'accepted' => true,
        ]);
        $this->assertDatabaseHas('consents', [
            'guest_identifier' => $guestIdentifier,
            'type' => ConsentType::CookieNecessary->value,
            'accepted' => true,
        ]);
    }

    #[Test]
    public function an_authenticated_user_stores_cookie_preferences_under_their_own_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/consent/cookies', [
            'categories' => ['analytics' => false, 'marketing' => true, 'preferences' => false],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('consents', [
            'user_id' => $user->id,
            'type' => ConsentType::CookieMarketing->value,
            'accepted' => true,
        ]);
    }

    #[Test]
    public function storing_cookie_preferences_requires_all_three_categories(): void
    {
        $response = $this->postJson('/api/v1/consent/cookies', [
            'guest_identifier' => '33333333-3333-3333-3333-333333333333',
            'categories' => ['analytics' => true],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['categories.marketing', 'categories.preferences']);
    }

    #[Test]
    public function a_later_submission_overrides_the_earlier_one_without_deleting_it(): void
    {
        $guestIdentifier = '44444444-4444-4444-4444-444444444444';

        $this->postJson('/api/v1/consent/cookies', [
            'guest_identifier' => $guestIdentifier,
            'categories' => ['analytics' => true, 'marketing' => true, 'preferences' => true],
        ])->assertCreated();

        $response = $this->postJson('/api/v1/consent/cookies', [
            'guest_identifier' => $guestIdentifier,
            'categories' => ['analytics' => false, 'marketing' => false, 'preferences' => false],
        ]);

        $response->assertCreated();
        $response->assertJson(['data' => [
            'necessary' => true,
            'analytics' => false,
            'marketing' => false,
            'preferences' => false,
        ]]);

        $this->assertDatabaseCount('consents', 8);
    }
}
