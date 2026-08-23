<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\SettingService;
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

    /** @var array{name: ?string, manager: ?string, company_id: ?string, address: ?string, email: ?string} */
    public readonly array $seller;

    public function __construct(public readonly Order $order)
    {
        // OrderPlaced (which triggers this mail) implements
        // ShouldDispatchAfterCommit, so by the time this runs the payment
        // CheckoutController::placeOrder() initiates in the same
        // transaction already exists — safe to load and describe it here
        // instead of the old "payment comes in a later step" placeholder.
        //
        // items.productVariant.product.primaryMedia is for the product
        // thumbnails in the item list — deliberately tolerant of a missing
        // link (deleted variant/product), same as OrderResource's own
        // product_variant_id note, since these are historical snapshots.
        $this->order->loadMissing([
            'items.productVariant.product.primaryMedia',
            'payments' => fn ($query) => $query->latest()->limit(1),
        ]);

        // Public property, not passed via Content::with() — Mailable
        // auto-exposes public properties to the view (same as $order),
        // which is how emails.partials.legal-disclosures (included from
        // the confirmation view) gets it.
        $this->seller = app(SettingService::class)->sellerIdentity();
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
