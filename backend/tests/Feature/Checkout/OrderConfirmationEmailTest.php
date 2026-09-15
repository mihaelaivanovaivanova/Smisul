<?php

namespace Tests\Feature\Checkout;

use App\Enums\Currency;
use App\Enums\LegalDocumentType;
use App\Mail\AdminOrderNotificationMail;
use App\Mail\OrderConfirmationMail;
use App\Models\LegalDocument;
use App\Models\Order;
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
     * Cash on delivery is live again, for Speedy orders only (see
     * PaymentService::availablePaymentMethods()) — Speedy's own courier
     * collects cash or a card payment in person at hand-off, unlike BOX
     * NOW's old (removed) COD mechanic, which was actually a card charge
     * through BOX NOW's own payment portal at pickup. Placing a COD order
     * reaches OrderStatus::Confirmed synchronously within the same
     * checkout request (see PaymentService::initiate()'s cash-on-delivery
     * branch and OrderStatus's own docblock for why that's a separate case
     * from Paid) — SendOrderStatusEmails treats it exactly like Paid, so
     * this fires straight off placeOrder(), no separate confirmation step
     * needed.
     */
    #[Test]
    public function a_cash_on_delivery_order_confirmation_email_describes_paying_the_speedy_courier(): void
    {
        Mail::fake();

        $this->placeOrder(['payment_method' => 'cash_on_delivery'])->assertCreated();

        Mail::assertSent(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) {
            $rendered = $mail->render();

            return $mail->hasTo('ivan@example.com')
                && str_contains($rendered, 'Плащаш в брой или с карта директно на куриера на Спиди в момента на предаване на пратката')
                && ! str_contains($rendered, 'Плащането с карта е потвърдено');
        });
    }
}
