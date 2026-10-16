<?php

namespace Tests\Feature\Shipping;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingCarrier;
use App\Mail\ShipmentReadyForPickupMail;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\ShipmentPickupReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers ShipmentPickupReminderService's escalation (immediate / +3 days /
 * +6 days, each only while still unpicked) in isolation from
 * ShipmentTrackingSyncService, which only ever calls sendDueReminders()
 * once at the end of its own sync() - see ShipmentTrackingSyncTest for the
 * "a shipment just reached ReadyForPickup" end-to-end case.
 */
class ShipmentPickupReminderTest extends TestCase
{
    use RefreshDatabase;

    private function readyForPickupShipment(array $overrides = []): Shipment
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Shipped,
            'customer_email' => 'ivan@example.com',
        ]);

        return Shipment::factory()->for($order)->created()->create(array_merge([
            'carrier' => ShippingCarrier::BoxNow,
            'status' => ShipmentStatus::ReadyForPickup,
            'ready_for_pickup_at' => now(),
        ], $overrides));
    }

    /**
     * The rollout kill switch (config('services.shipping.
     * pickup_reminders_enabled'), SHIPMENT_PICKUP_REMINDERS_ENABLED) -
     * false in production right now, by request, until Speedy's real
     * operation code is confirmed. phpunit.xml flips this to true for
     * every other test in this file; this one explicitly overrides it
     * back to false to cover the switch itself.
     */
    #[Test]
    public function nothing_sends_while_the_feature_flag_is_disabled_even_when_fully_due(): void
    {
        config(['services.shipping.pickup_reminders_enabled' => false]);
        Mail::fake();

        $this->readyForPickupShipment(['ready_for_pickup_at' => now()->subDays(10)]);

        $result = app(ShipmentPickupReminderService::class)->sendDueReminders();

        $this->assertSame(['sent' => 0, 'failed' => 0], $result);
        Mail::assertNothingSent();
    }

    #[Test]
    public function the_first_reminder_sends_immediately_once_ready_for_pickup(): void
    {
        Mail::fake();

        $shipment = $this->readyForPickupShipment();

        $result = app(ShipmentPickupReminderService::class)->sendDueReminders();

        $this->assertSame(['sent' => 1, 'failed' => 0], $result);
        $this->assertNotNull($shipment->fresh()->pickup_reminder_sent_at);
        Mail::assertSent(
            ShipmentReadyForPickupMail::class,
            fn (ShipmentReadyForPickupMail $mail) => $mail->hasTo('ivan@example.com') && $mail->stage === 1,
        );
    }

    #[Test]
    public function the_first_reminder_never_sends_twice(): void
    {
        Mail::fake();

        $this->readyForPickupShipment(['pickup_reminder_sent_at' => now()->subHour()]);

        $result = app(ShipmentPickupReminderService::class)->sendDueReminders();

        $this->assertSame(['sent' => 0, 'failed' => 0], $result);
        Mail::assertNothingSent();
    }

    #[Test]
    public function the_second_reminder_is_not_due_before_three_days_have_passed(): void
    {
        Mail::fake();

        $this->readyForPickupShipment([
            'ready_for_pickup_at' => now()->subDays(2),
            'pickup_reminder_sent_at' => now()->subDays(2),
        ]);

        $result = app(ShipmentPickupReminderService::class)->sendDueReminders();

        $this->assertSame(['sent' => 0, 'failed' => 0], $result);
        Mail::assertNothingSent();
    }

    #[Test]
    public function the_second_reminder_sends_once_three_days_have_passed_still_unpicked(): void
    {
        Mail::fake();

        $shipment = $this->readyForPickupShipment([
            'ready_for_pickup_at' => now()->subDays(3),
            'pickup_reminder_sent_at' => now()->subDays(3),
        ]);

        $result = app(ShipmentPickupReminderService::class)->sendDueReminders();

        $this->assertSame(['sent' => 1, 'failed' => 0], $result);
        $this->assertNotNull($shipment->fresh()->pickup_reminder_2_sent_at);
        Mail::assertSent(ShipmentReadyForPickupMail::class, fn (ShipmentReadyForPickupMail $mail) => $mail->stage === 2);
    }

    #[Test]
    public function the_third_and_final_reminder_sends_once_six_days_have_passed_still_unpicked(): void
    {
        Mail::fake();

        $shipment = $this->readyForPickupShipment([
            'ready_for_pickup_at' => now()->subDays(6),
            'pickup_reminder_sent_at' => now()->subDays(6),
            'pickup_reminder_2_sent_at' => now()->subDays(3),
        ]);

        $result = app(ShipmentPickupReminderService::class)->sendDueReminders();

        $this->assertSame(['sent' => 1, 'failed' => 0], $result);
        $this->assertNotNull($shipment->fresh()->pickup_reminder_3_sent_at);
        Mail::assertSent(ShipmentReadyForPickupMail::class, fn (ShipmentReadyForPickupMail $mail) => $mail->stage === 3);
    }

    #[Test]
    public function nothing_sends_once_all_three_reminders_are_already_sent(): void
    {
        Mail::fake();

        $this->readyForPickupShipment([
            'ready_for_pickup_at' => now()->subDays(10),
            'pickup_reminder_sent_at' => now()->subDays(10),
            'pickup_reminder_2_sent_at' => now()->subDays(7),
            'pickup_reminder_3_sent_at' => now()->subDays(4),
        ]);

        $result = app(ShipmentPickupReminderService::class)->sendDueReminders();

        $this->assertSame(['sent' => 0, 'failed' => 0], $result);
        Mail::assertNothingSent();
    }

    /**
     * A shipment that's already been picked up (Delivered) must never get
     * a pickup reminder, even if its old reminder timestamps are still
     * unset for some reason - the scope only ever matches
     * ShipmentStatus::ReadyForPickup.
     */
    #[Test]
    public function a_delivered_shipment_never_gets_a_pickup_reminder(): void
    {
        Mail::fake();

        $this->readyForPickupShipment([
            'status' => ShipmentStatus::Delivered,
            'ready_for_pickup_at' => now()->subDays(10),
        ]);

        $result = app(ShipmentPickupReminderService::class)->sendDueReminders();

        $this->assertSame(['sent' => 0, 'failed' => 0], $result);
        Mail::assertNothingSent();
    }

    /**
     * One run only ever sends at most one stage per shipment, oldest-due
     * first - a shipment that's gone 7+ days without sync ever running
     * gets stage 1 now, not stage 3 - it'll pick up stage 2 and 3 on
     * later runs once their own waits have elapsed from the real
     * ready_for_pickup_at, same as any shipment synced on time would.
     */
    #[Test]
    public function only_the_oldest_due_stage_sends_in_a_single_run(): void
    {
        Mail::fake();

        $shipment = $this->readyForPickupShipment(['ready_for_pickup_at' => now()->subDays(10)]);

        $result = app(ShipmentPickupReminderService::class)->sendDueReminders();

        $this->assertSame(['sent' => 1, 'failed' => 0], $result);
        $shipment->refresh();
        $this->assertNotNull($shipment->pickup_reminder_sent_at);
        $this->assertNull($shipment->pickup_reminder_2_sent_at);
        $this->assertNull($shipment->pickup_reminder_3_sent_at);
        Mail::assertSentCount(1);
    }

    #[Test]
    public function a_mail_transport_failure_leaves_the_stage_eligible_for_retry(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

        $shipment = $this->readyForPickupShipment();

        $result = app(ShipmentPickupReminderService::class)->sendDueReminders();

        $this->assertSame(['sent' => 0, 'failed' => 1], $result);
        $this->assertNull($shipment->fresh()->pickup_reminder_sent_at);
    }
}
