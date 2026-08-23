<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\InvoiceNumberGenerator;
use App\Services\SettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent alongside OrderDeliveredMail for every delivered order — see
 * SendOrderStatusEmails. Reuses the same invoices.order view
 * OrderController::invoice() renders on demand, attached here as a
 * downloadable file rather than requiring the customer to log back in and
 * fetch it themselves. Assigns the order's invoice_number here too (via
 * InvoiceNumberGenerator, idempotent) in case this is the first time the
 * invoice is ever issued for this order — a customer who never visited
 * the on-demand download endpoint would otherwise get an unnumbered one.
 */
class OrderInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order)
    {
        $this->order->loadMissing('items');
        app(InvoiceNumberGenerator::class)->generateFor($this->order);
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
                fn () => view('invoices.order', [
                    'order' => $this->order,
                    'seller' => app(SettingService::class)->sellerIdentity(),
                ])->render(),
                "invoice-{$this->order->order_number}.html",
            )->withMime('text/html'),
        ];
    }
}
