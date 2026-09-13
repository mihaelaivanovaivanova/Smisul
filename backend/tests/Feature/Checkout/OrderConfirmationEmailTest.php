<?php

namespace Tests\Feature\Checkout;

use App\Enums\Currency;
use App\Enums\LegalDocumentType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use App\Mail\AdminOrderNotificationMail;
use App\Mail\OrderConfirmationMail;
use App\Models\LegalDocument;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderConfirmationEmailTest extends TestCase
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
    private function placeOrder(array $overrides = [])
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 15]);
        $cart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);

        return $this->withHeaders(['X-Guest-Cart-Token' => $cart->json('meta.guest_token')])
            ->postJson('/api/v1/checkout/orders', array_merge([
                'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456'],
                'address' => ['country' => 'Bulgaria', 'city' => 'Sofia', 'postal_code' => '1000', 'address_line' => 'ul. Vitosha 1'],
                'shipping_carrier' => 'speedy',
                'shipping_delivery_type' => 'address',
                'legal_document_ids' => $this->legalDocuments(),
            ], $overrides));
    }

    #[Test]
    public function a_paid_order_notifies_the_customer_and_each_store_recipient_with_an_embedded_logo(): void
    {
        $mailer = app('mail.manager');
        Mail::fake();

        $response = $this->placeOrder()->assertCreated();
        app(OrderService::class)->confirmPayment(Order::findOrFail($response->json('data.id')));

        Mail::assertSent(OrderConfirmationMail::class, 1);
        Mail::assertSent(AdminOrderNotificationMail::class, 3);

        foreach (['admin@smisul.bg', 'filchevweb@gmail.com', 'mihaela.ivanova.ivanova@gmail.com'] as $recipient) {
            Mail::assertSent(AdminOrderNotificationMail::class, function (AdminOrderNotificationMail $mail) use ($recipient) {
                return $mail->hasTo($recipient)
                    && count($mail->to) === 1
                    && str_contains($mail->render(), $mail->order->order_number);
            });
        }

        // Use the array transport to inspect the real MIME message without sending email.
        Mail::swap($mailer);
        $order = Order::latest('id')->firstOrFail();
        foreach ([new OrderConfirmationMail($order), new AdminOrderNotificationMail($order)] as $mail) {
            $sent = Mail::mailer('array')->to('test@example.com')->send($mail);
            $message = $sent->getSymfonySentMessage()->getOriginalMessage();
            $this->assertStringContainsString($order->order_number, $message->getSubject());
            $this->assertStringContainsString('cid:', $message->getHtmlBody());
            $this->assertCount(1, $message->getAttachments());
            $this->assertSame('image/png', $message->getAttachments()[0]->getMediaType().'/'.$message->getAttachments()[0]->getMediaSubtype());
        }
    }

    #[Test]
    public function a_paid_card_order_confirmation_email_says_payment_is_confirmed(): void
    {
        Mail::fake();

        $response = $this->placeOrder()->assertCreated();
        app(OrderService::class)->confirmPayment(Order::findOrFail($response->json('data.id')));

        Mail::assertSent(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) {
            $rendered = $mail->render();

            return $mail->hasTo('ivan@example.com')
                && str_contains($rendered, 'Плащането с карта е потвърдено')
                && ! str_contains($rendered, 'следваща стъпка')
                && ! str_contains($rendered, 'BOX NOW');
        });
    }

    /**
     * Cash on delivery can no longer be newly selected at checkout (see
     * PaymentMethod::active()), so this builds a historical order/payment
     * directly via factories — payment_method = cash_on_delivery, the
     * enum case kept exactly so an order like this one still renders
     * correctly — and renders the Mailable directly rather than going
     * through the checkout endpoint.
     */
    #[Test]
    public function a_historical_cash_on_delivery_order_confirmation_email_says_so(): void
    {
        $order = Order::factory()->create([
            'shipping_carrier' => ShippingCarrier::BoxNow,
            'shipping_delivery_type' => ShippingDeliveryType::Locker,
            'shipping_office_id' => 'locker-1',
            'shipping_office_name' => 'BOX NOW Mall of Sofia',
            'shipping_city' => 'Sofia',
        ]);
        Payment::factory()->for($order)->create([
            'payment_method' => PaymentMethod::CashOnDelivery,
            'provider' => PaymentProvider::CashOnDelivery,
        ]);

        $rendered = (new OrderConfirmationMail($order))->render();

        // Not "в брой на куриера" — BOX NOW is an unmanned locker
        // network, so cash-on-delivery there was actually a card charge
        // through BOX NOW's own payment portal at pickup, not cash
        // handed to a courier.
        $this->assertStringContainsString('Плащаш с банкова карта чрез BOX NOW', $rendered);
        $this->assertStringNotContainsString('Обработваме плащането с карта', $rendered);
    }
}
