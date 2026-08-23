<?php

namespace Tests\Feature\Checkout;

use App\Enums\Currency;
use App\Enums\LegalDocumentType;
use App\Enums\OrderStatus;
use App\Mail\OrderDeliveredMail;
use App\Mail\OrderInvoiceMail;
use App\Models\LegalDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderInvoiceEmailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<int>
     */
    private function legalDocuments(): array
    {
        return collect(LegalDocumentType::cases())->map(function (LegalDocumentType $type) {
            $existing = LegalDocument::where('type', $type)->where('version', '1.0')->first();

            return $existing?->id ?? LegalDocument::factory()->create(['type' => $type, 'version' => '1.0'])->id;
        })->all();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function placeOrder(array $overrides = []): Order
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 15]);
        $cart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);

        $placed = $this->withHeaders(['X-Guest-Cart-Token' => $cart->json('meta.guest_token')])
            ->postJson('/api/v1/checkout/orders', array_merge([
                'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456'],
                'address' => ['country' => 'Bulgaria', 'city' => 'Sofia', 'postal_code' => '1000', 'address_line' => 'ul. Vitosha 1'],
                'shipping_carrier' => 'speedy',
                'shipping_delivery_type' => 'address',
                'legal_document_ids' => $this->legalDocuments(),
            ], $overrides))
            ->assertCreated();

        return Order::findOrFail($placed->json('data.id'));
    }

    private function deliver(Order $order): void
    {
        $status = app(OrderStatusService::class);
        foreach ([OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Packed, OrderStatus::Shipped, OrderStatus::Delivered] as $to) {
            $status->transitionTo($order, $to, null);
        }
    }

    #[Test]
    public function wants_invoice_is_persisted_on_the_order(): void
    {
        $order = $this->placeOrder([
            'wants_invoice' => true,
            'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456', 'company' => 'ACME EOOD', 'vat_number' => 'BG123456789'],
            'billing_same_as_shipping' => true,
        ]);

        $this->assertTrue($order->fresh()->wants_invoice);
    }

    #[Test]
    public function wants_invoice_defaults_to_false_when_omitted(): void
    {
        $order = $this->placeOrder();

        $this->assertFalse($order->fresh()->wants_invoice);
    }

    #[Test]
    public function an_invoice_email_is_sent_on_delivery_when_wants_invoice_was_checked(): void
    {
        Mail::fake();

        $order = $this->placeOrder([
            'wants_invoice' => true,
            'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456', 'company' => 'ACME EOOD', 'vat_number' => 'BG123456789'],
            'billing_same_as_shipping' => true,
        ]);

        $this->deliver($order);

        Mail::assertSent(OrderDeliveredMail::class, fn (OrderDeliveredMail $mail) => $mail->hasTo('ivan@example.com'));

        Mail::assertSent(OrderInvoiceMail::class, function (OrderInvoiceMail $mail) {
            $rendered = $mail->render();

            return $mail->hasTo('ivan@example.com')
                && str_contains($rendered, 'Фактурата за поръчка')
                && $mail->attachments() !== [];
        });
    }

    /**
     * The sales document (титled "Фактура" per Bulgarian convention, but
     * legally just a чл. 6 ЗСч first-level accounting document since
     * Smisul isn't VAT-registered — see OrderController::invoice()'s
     * docblock) is required for every sale, not only when the customer
     * separately asked for billing/company details. wants_invoice only
     * gates whether a distinct billing address was collected.
     */
    #[Test]
    public function an_invoice_email_is_still_sent_on_delivery_when_wants_invoice_was_not_checked(): void
    {
        Mail::fake();

        $order = $this->placeOrder();

        $this->deliver($order);

        Mail::assertSent(OrderDeliveredMail::class);
        Mail::assertSent(OrderInvoiceMail::class, function (OrderInvoiceMail $mail) {
            $rendered = $mail->render();

            return $mail->hasTo('ivan@example.com')
                && str_contains($rendered, 'Фактурата за поръчка')
                && $mail->attachments() !== [];
        });
    }

    #[Test]
    public function no_invoice_email_is_sent_on_earlier_transitions_even_when_wants_invoice_was_checked(): void
    {
        Mail::fake();

        $order = $this->placeOrder([
            'wants_invoice' => true,
            'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456', 'company' => 'ACME EOOD', 'vat_number' => 'BG123456789'],
            'billing_same_as_shipping' => true,
        ]);

        app(OrderStatusService::class)->transitionTo($order, OrderStatus::Paid, null);

        Mail::assertNotSent(OrderInvoiceMail::class);
    }
}
