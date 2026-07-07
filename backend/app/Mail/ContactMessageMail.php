<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $senderEmail,
        public readonly string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Запитване от сайта от {$this->name}",
            replyTo: [new Address($this->senderEmail, $this->name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact-message');
    }
}
