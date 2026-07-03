<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent synchronously (no ShouldQueue) — matches every other transactional
 * email in this app (see AppServiceProvider's VerifyEmail/ResetPassword
 * wiring), so delivery doesn't silently depend on a queue worker running.
 */
class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order)
    {
        $this->order->loadMissing('items');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Потвърждение на поръчка {$this->order->order_number}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.orders.confirmation');
    }
}
