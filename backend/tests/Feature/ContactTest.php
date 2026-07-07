<?php

namespace Tests\Feature;

use App\Mail\ContactMessageMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_guest_can_submit_the_contact_form(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/contact', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'message' => 'Здравейте, имам въпрос за продукт.',
        ]);

        $response->assertCreated();

        Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail) {
            return $mail->name === 'Ada Lovelace'
                && $mail->senderEmail === 'ada@example.com'
                && $mail->body === 'Здравейте, имам въпрос за продукт.'
                && $mail->hasTo(config('mail.contact_address'))
                && $mail->envelope()->replyTo[0]->address === 'ada@example.com';
        });
    }

    #[Test]
    public function name_email_and_message_are_required(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/contact', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name', 'email', 'message']);
        Mail::assertNothingSent();
    }

    #[Test]
    public function the_email_field_must_be_a_valid_email(): void
    {
        $response = $this->postJson('/api/v1/contact', [
            'name' => 'Ada Lovelace',
            'email' => 'not-an-email',
            'message' => 'Здравейте.',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }
}
