<?php

namespace Tests\Feature\Shipping;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingCarrier;
use App\Mail\ShipmentPickupNotificationMail;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\ShipmentPickupNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShipmentPickupNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function eligibleOrder(array $shipmentOverrides = []): Order
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Shipped,
            'shipping_carrier' => ShippingCarrier::Speedy,
            'customer_email' => 'ivan@example.com',
        ]);
        Shipment::factory()->for($order)->created()->create(array_merge([
            'carrier' => ShippingCarrier::Speedy,
            'status' => ShipmentStatus::ReadyForPickup,
            'office_name' => 'Офис 10 - Русе',
        ], $shipmentOverrides));

        return $order;
    }

    #[Test]
    public function a_shipped_speedy_order_with_an_office_name_is_eligible(): void
    {
        $order = $this->eligibleOrder();

        $this->assertTrue(ShipmentPickupNotificationService::isEligible($order));
    }

    #[Test]
    public function an_order_not_currently_shipped_is_not_eligible(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Delivered, 'shipping_carrier' => ShippingCarrier::Speedy]);
        Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::Speedy,
            'office_name' => 'Офис 10 - Русе',
        ]);

        $this->assertFalse(ShipmentPickupNotificationService::isEligible($order));
    }

    /** Speedy only, by request - BOX NOW's own locker notification is reliable enough already. */
    #[Test]
    public function a_box_now_order_is_not_eligible(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'shipping_carrier' => ShippingCarrier::BoxNow]);
        Shipment::factory()->for($order)->created()->create([
            'carrier' => ShippingCarrier::BoxNow,
            'office_name' => 'BOX NOW - Mall Sofia',
        ]);

        $this->assertFalse(ShipmentPickupNotificationService::isEligible($order));
    }

    /** A home-address delivery has no office to collect from, so no office_name is ever set for it. */
    #[Test]
    public function a_speedy_order_with_no_office_name_is_not_eligible(): void
    {
        $order = $this->eligibleOrder(['office_name' => null]);

        $this->assertFalse(ShipmentPickupNotificationService::isEligible($order));
    }

    #[Test]
    public function an_order_with_no_shipment_is_not_eligible(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'shipping_carrier' => ShippingCarrier::Speedy]);

        $this->assertFalse(ShipmentPickupNotificationService::isEligible($order));
    }

    #[Test]
    public function sending_updates_pickup_notification_sent_at_and_leaves_it_eligible_again(): void
    {
        Mail::fake();
        $order = $this->eligibleOrder();

        $result = app(ShipmentPickupNotificationService::class)->send($order);

        $this->assertTrue($result['sent']);
        $this->assertNull($result['reason']);
        $this->assertNotNull($order->shipment->fresh()->pickup_notification_sent_at);
        // Repeatable, unlike the 30-day reminder - still eligible right
        // after a send, since an admin may need to nudge the customer
        // again.
        $this->assertTrue(ShipmentPickupNotificationService::isEligible($order->fresh()));
        Mail::assertSent(ShipmentPickupNotificationMail::class, fn (ShipmentPickupNotificationMail $mail) => $mail->hasTo('ivan@example.com'));
    }

    #[Test]
    public function sending_for_an_ineligible_order_does_nothing(): void
    {
        Mail::fake();
        $order = Order::factory()->create(['status' => OrderStatus::Packed, 'shipping_carrier' => ShippingCarrier::Speedy]);

        $result = app(ShipmentPickupNotificationService::class)->send($order);

        $this->assertFalse($result['sent']);
        $this->assertNotNull($result['reason']);
        Mail::assertNothingSent();
    }

    /**
     * Mirrors OrderReminderService::sendReminder()'s own resilience
     * convention: a mail transport failure is logged and skipped, never
     * thrown, and never marks pickup_notification_sent_at. A real
     * unreachable port, not Mail::fake() - a fake never exercises the
     * try/catch at all (see OrderThirtyDayReminderTest's own equivalent).
     */
    #[Test]
    public function a_mail_transport_failure_is_reported_without_marking_it_sent(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
        $order = $this->eligibleOrder();

        $result = app(ShipmentPickupNotificationService::class)->send($order);

        $this->assertFalse($result['sent']);
        $this->assertSame('Sending failed - see the logs.', $result['reason']);
        $this->assertNull($order->shipment->fresh()->pickup_notification_sent_at);
    }
}
