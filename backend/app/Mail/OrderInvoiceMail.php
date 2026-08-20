<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent alongside OrderDeliveredMail, only when the order has wants_invoice
 * set — see SendOrderStatusEmails. Reuses the same invoices.order view
 * OrderController::invoice() renders on demand, attached here as a
 * downloadable file rather than requiring the customer to log back in and
 * fetch it themselves.
 */
class OrderInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order)
    {
        $this->order->loadMissing('items');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Фактура за поръчка {$this->order->order_number}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.orders.invoice');
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => view('invoices.order', ['order' => $this->order])->render(),
                "invoice-{$this->order->order_number}.html",
            )->withMime('text/html'),
        ];
    }
}
