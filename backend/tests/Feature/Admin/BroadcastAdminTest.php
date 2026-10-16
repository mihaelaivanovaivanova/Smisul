<?php

namespace Tests\Feature\Admin;

use App\Mail\BroadcastMail;
use App\Models\EmailSuppression;
use App\Models\FunnelLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BroadcastAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_customer_cannot_access_broadcast_endpoints(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->getJson('/api/v1/admin/broadcasts/recipient-count')->assertForbidden();
        $this->actingAs($customer)->postJson('/api/v1/admin/broadcasts', [
            'subject' => 'Hello',
            'body' => 'Body',
        ])->assertForbidden();
    }

    #[Test]
    public function a_guest_is_unauthenticated_on_broadcast_endpoints(): void
    {
        $this->getJson('/api/v1/admin/broadcasts/recipient-count')->assertUnauthorized();
    }

    #[Test]
    public function recipient_count_combines_consenting_users_and_funnel_leads_minus_suppressions(): void
    {
        // marketing_consent/newsletter_subscription default to a random
        // boolean in UserFactory::definition() - every user in this test
        // pins both explicitly, or the expected count would be flaky.
        $admin = User::factory()->administrator()->create(['marketing_consent' => false, 'newsletter_subscription' => false]);
        User::factory()->create(['email' => 'opted-in@example.com', 'marketing_consent' => true, 'newsletter_subscription' => false]);
        User::factory()->create(['email' => 'not-opted-in@example.com', 'marketing_consent' => false, 'newsletter_subscription' => false]);
        FunnelLead::create(['email' => 'lead@example.com']);
        FunnelLead::create(['email' => 'unsubscribed-lead@example.com']);
        EmailSuppression::create(['email' => 'unsubscribed-lead@example.com', 'reason' => 'unsubscribed']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/broadcasts/recipient-count');

        $response->assertOk();
        $response->assertJsonPath('data.count', 2);
    }

    #[Test]
    public function sending_a_broadcast_only_reaches_opted_in_recipients_and_carries_an_unsubscribe_link(): void
    {
        Mail::fake();

        $admin = User::factory()->administrator()->create(['marketing_consent' => false, 'newsletter_subscription' => false]);
        User::factory()->create(['email' => 'opted-in@example.com', 'newsletter_subscription' => true, 'marketing_consent' => false]);
        User::factory()->create(['email' => 'not-opted-in@example.com', 'marketing_consent' => false, 'newsletter_subscription' => false]);
        FunnelLead::create(['email' => 'lead@example.com']);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/broadcasts', [
            'subject' => 'New Miswak accessories',
            'body' => "First paragraph.\n\nSecond paragraph.",
            'cta_url' => 'https://smisul.bg/products/miswak',
            'cta_label' => 'Shop now',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.sent', 2);
        $response->assertJsonPath('data.total', 2);

        Mail::assertSentCount(2);
        Mail::assertSent(BroadcastMail::class, fn (BroadcastMail $mail) => $mail->hasTo('opted-in@example.com'));
        Mail::assertSent(BroadcastMail::class, fn (BroadcastMail $mail) => $mail->hasTo('lead@example.com'));
        Mail::assertNotSent(BroadcastMail::class, fn (BroadcastMail $mail) => $mail->hasTo('not-opted-in@example.com'));
    }

    /**
     * By request: broadcasts go out from a separate sender identity
     * (config('mail.promotional_from')) than order confirmations/review
     * confirmations, so Gmail's per-sender Promotions-tab reputation for
     * this bulk, discount-heavy mail doesn't bleed into the purely
     * transactional mail sharing config('mail.from') would otherwise get -
     * see BroadcastMail's own docblock. Shared with the 30-day reminder
     * (same reasoning, see OrderThirtyDayReminderMail), by request -
     * that's covered by OrderThirtyDayReminderTest, not here.
     */
    #[Test]
    public function a_broadcast_is_sent_from_the_separate_promotional_sender_address(): void
    {
        Mail::fake();

        $admin = User::factory()->administrator()->create(['marketing_consent' => false, 'newsletter_subscription' => false]);
        User::factory()->create(['email' => 'opted-in@example.com', 'marketing_consent' => true, 'newsletter_subscription' => false]);

        $this->actingAs($admin)->postJson('/api/v1/admin/broadcasts', [
            'subject' => 'New Miswak accessories',
            'body' => 'Body',
        ])->assertOk();

        Mail::assertSent(
            BroadcastMail::class,
            fn (BroadcastMail $mail) => $mail->hasFrom(config('mail.promotional_from.address'), config('mail.promotional_from.name')),
        );
    }

    #[Test]
    public function a_customer_cannot_send_a_broadcast_by_posting_directly(): void
    {
        Mail::fake();

        $customer = User::factory()->create();
        User::factory()->create(['email' => 'opted-in@example.com', 'marketing_consent' => true]);

        $this->actingAs($customer)->postJson('/api/v1/admin/broadcasts', [
            'subject' => 'Hello',
            'body' => 'Body',
        ])->assertForbidden();

        Mail::assertNothingSent();
    }
}
