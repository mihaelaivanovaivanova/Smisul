<?php

namespace Tests\Feature\Orders;

use App\Enums\Currency;
use App\Enums\OrderStatus;
use App\Enums\VariantStatus;
use App\Mail\OrderDeliveredMail;
use App\Mail\OrderHandbookMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductBundle;
use App\Services\OrderHandbookService;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderHandbookServiceTest extends TestCase
{
    use RefreshDatabase;

    private function kitLineOrder(array $orderOverrides = []): Order
    {
        $order = Order::factory()->create(array_merge([
            'status' => OrderStatus::Delivered,
            'customer_email' => 'ivan@example.com',
        ], $orderOverrides));

        $bundle = ProductBundle::create([
            'slug' => 'test-kit',
            'name' => 'Test Kit',
            'currency' => Currency::EUR->value,
            'amount' => 29.99,
            'status' => VariantStatus::Active->value,
        ]);

        OrderItem::factory()->for($order)->create([
            'bundle_id' => $bundle->id,
            'bundle_name' => $bundle->name,
        ]);

        return $order;
    }

    #[Test]
    public function a_delivered_order_with_a_kit_line_is_eligible(): void
    {
        $order = $this->kitLineOrder();

        $this->assertTrue(OrderHandbookService::isEligible($order));
    }

    #[Test]
    public function an_order_not_yet_delivered_is_not_eligible(): void
    {
        $order = $this->kitLineOrder(['status' => OrderStatus::Shipped]);

        $this->assertFalse(OrderHandbookService::isEligible($order));
    }

    #[Test]
    public function a_delivered_order_with_no_kit_line_is_not_eligible(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Delivered, 'customer_email' => 'ivan@example.com']);
        OrderItem::factory()->for($order)->create();

        $this->assertFalse(OrderHandbookService::isEligible($order));
    }

    #[Test]
    public function a_manual_order_with_no_email_is_not_eligible(): void
    {
        $order = $this->kitLineOrder(['customer_email' => null]);

        $this->assertFalse(OrderHandbookService::isEligible($order));
    }

    #[Test]
    public function sending_updates_handbook_email_sent_at_and_leaves_it_eligible_again(): void
    {
        Mail::fake();
        $order = $this->kitLineOrder();

        $result = app(OrderHandbookService::class)->send($order);

        $this->assertTrue($result['sent']);
        $this->assertNull($result['reason']);
        $this->assertNotNull($order->fresh()->handbook_email_sent_at);
        // Repeatable, not a one-shot flag - still eligible right after a
        // send, since an admin may need to resend it (e.g. the customer
        // says they never got it).
        $this->assertTrue(OrderHandbookService::isEligible($order->fresh()->load('items')));
        Mail::assertSent(OrderHandbookMail::class, fn (OrderHandbookMail $mail) => $mail->hasTo('ivan@example.com'));
    }

    #[Test]
    public function sending_for_an_ineligible_order_does_nothing(): void
    {
        Mail::fake();
        $order = Order::factory()->create(['status' => OrderStatus::Processing]);

        $result = app(OrderHandbookService::class)->send($order->load('items'));

        $this->assertFalse($result['sent']);
        $this->assertNotNull($result['reason']);
        Mail::assertNothingSent();
    }

    /**
     * Mirrors ShipmentPickupNotificationServiceTest's own equivalent - a
     * real unreachable port, not Mail::fake(), so the try/catch is
     * actually exercised.
     */
    #[Test]
    public function a_mail_transport_failure_is_reported_without_marking_it_sent(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
        $order = $this->kitLineOrder();

        $result = app(OrderHandbookService::class)->send($order);

        $this->assertFalse($result['sent']);
        $this->assertSame('Sending failed - see the logs.', $result['reason']);
        $this->assertNull($order->fresh()->handbook_email_sent_at);
    }

    /**
     * End-to-end: the Delivered transition itself (not just the service in
     * isolation) actually triggers a kit order's handbook attachment, via
     * SendOrderStatusEmails - the real path production orders go through.
     * By request, a kit order's handbook rides along on OrderDeliveredMail
     * itself rather than a separate email - see that Mailable's own
     * docblock - so this asserts OrderDeliveredMail carries it and that
     * OrderHandbookMail (now resend-button-only) is NOT also sent.
     */
    #[Test]
    public function transitioning_a_kit_order_to_delivered_attaches_the_handbook_to_the_delivered_email(): void
    {
        Mail::fake();
        $order = $this->kitLineOrder(['status' => OrderStatus::Shipped]);

        app(OrderStatusService::class)->transitionTo($order, OrderStatus::Delivered, null);

        Mail::assertSent(OrderDeliveredMail::class, function (OrderDeliveredMail $mail) {
            return $mail->hasTo('ivan@example.com') && $mail->hasKit && count($mail->attachments()) === 1;
        });
        Mail::assertNotSent(OrderHandbookMail::class);
        $this->assertNotNull($order->fresh()->handbook_email_sent_at);
    }

    #[Test]
    public function transitioning_a_non_kit_order_to_delivered_sends_the_delivered_email_without_a_handbook(): void
    {
        Mail::fake();
        $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'customer_email' => 'ivan@example.com']);
        OrderItem::factory()->for($order)->create();

        app(OrderStatusService::class)->transitionTo($order, OrderStatus::Delivered, null);

        Mail::assertSent(OrderDeliveredMail::class, fn (OrderDeliveredMail $mail) => ! $mail->hasKit && $mail->attachments() === []);
        Mail::assertNotSent(OrderHandbookMail::class);
        $this->assertNull($order->fresh()->handbook_email_sent_at);
    }
}
