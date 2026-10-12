<?php

namespace Tests\Feature;

use App\Enums\ConsentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_post_to_the_signed_link_one_click_unsubscribes_with_no_body(): void
    {
        $url = URL::signedRoute('unsubscribe', ['email' => 'lead@example.com']);

        $response = $this->postJson($url);

        $response->assertNoContent();
        $this->assertDatabaseHas('email_suppressions', ['email' => 'lead@example.com', 'reason' => 'unsubscribed']);
    }

    #[Test]
    public function a_get_on_the_signed_link_also_unsubscribes_and_shows_a_confirmation_page(): void
    {
        $url = URL::signedRoute('unsubscribe', ['email' => 'lead@example.com']);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('lead@example.com');
        $this->assertDatabaseHas('email_suppressions', ['email' => 'lead@example.com']);
    }

    #[Test]
    public function an_unsigned_or_tampered_link_is_rejected_and_does_not_unsubscribe_anyone(): void
    {
        $response = $this->postJson('/api/v1/unsubscribe/someone@example.com');

        $response->assertForbidden();
        $this->assertDatabaseMissing('email_suppressions', ['email' => 'someone@example.com']);
    }

    #[Test]
    public function unsubscribing_twice_is_idempotent(): void
    {
        $url = URL::signedRoute('unsubscribe', ['email' => 'lead@example.com']);

        $this->postJson($url)->assertNoContent();
        $this->postJson($url)->assertNoContent();

        $this->assertDatabaseCount('email_suppressions', 1);
    }

    #[Test]
    public function unsubscribing_a_registered_user_also_withdraws_their_account_marketing_consent(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'marketing_consent' => true,
            'newsletter_subscription' => true,
        ]);

        $url = URL::signedRoute('unsubscribe', ['email' => 'customer@example.com']);

        $this->postJson($url)->assertNoContent();

        $user->refresh();
        $this->assertFalse($user->marketing_consent);
        $this->assertFalse($user->newsletter_subscription);
        $this->assertDatabaseHas('consents', [
            'user_id' => $user->id,
            'type' => ConsentType::Marketing->value,
            'accepted' => false,
        ]);
    }
}
