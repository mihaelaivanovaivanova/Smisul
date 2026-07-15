<?php

namespace Tests\Feature;

use App\Mail\FunnelLeadWelcomeMail;
use App\Models\FunnelLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FunnelLeadTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_guest_can_leave_their_email(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/funnel/leads', [
            'email' => 'ada@example.com',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('funnel_leads', ['email' => 'ada@example.com']);
    }

    #[Test]
    public function a_welcome_email_is_sent_on_first_capture_only(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/funnel/leads', ['email' => 'ada@example.com'])->assertCreated();
        Mail::assertSent(FunnelLeadWelcomeMail::class, fn (FunnelLeadWelcomeMail $mail) => $mail->hasTo('ada@example.com'));

        $this->postJson('/api/v1/funnel/leads', ['email' => 'ada@example.com'])->assertCreated();
        Mail::assertSentCount(1);
    }

    #[Test]
    public function emails_are_stored_lowercased(): void
    {
        $this->postJson('/api/v1/funnel/leads', ['email' => 'Ada@Example.COM'])->assertCreated();

        $this->assertDatabaseHas('funnel_leads', ['email' => 'ada@example.com']);
    }

    #[Test]
    public function resubmitting_the_same_email_is_a_no_op_with_an_identical_response(): void
    {
        $first = $this->postJson('/api/v1/funnel/leads', ['email' => 'ada@example.com']);
        $second = $this->postJson('/api/v1/funnel/leads', ['email' => 'ada@example.com']);

        // Same status and body both times — the endpoint must not reveal
        // whether an email was already on the list.
        $second->assertStatus($first->status());
        $this->assertSame($first->json(), $second->json());
        $this->assertSame(1, FunnelLead::count());
    }

    #[Test]
    public function the_email_field_is_required_and_must_be_valid(): void
    {
        $this->postJson('/api/v1/funnel/leads', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/v1/funnel/leads', ['email' => 'not-an-email'])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertSame(0, FunnelLead::count());
    }
}
