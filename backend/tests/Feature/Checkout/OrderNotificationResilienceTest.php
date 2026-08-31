<?php

namespace Tests\Feature\Checkout;

use App\Enums\Currency;
use App\Enums\LegalDocumentType;
use App\Enums\OrderStatus;
use App\Enums\VariantStatus;
use App\Models\LegalDocument;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * A mail transport failure (SMTP unreachable, DNS hiccup, etc.) must never
 * block or roll back a real business event - the order/status change
 * already happened and is real, even if the customer's own confirmation
 * email failed to send. Found directly: a local mail server outage during
 * manual testing silently rolled back an admin's "mark as cancelled"
 * action with no error surfaced, and separately turned a real, successful
 * order placement into a 500 response. See SendOrderPlacedNotifications
 * and SendOrderStatusEmails - both now log and swallow mail failures
 * rather than letting them propagate, mirroring
 * Listeners\CreateShipmentOnOrderPaid's own established pattern.
 */
class OrderNotificationResilienceTest extends TestCase
{
    use RefreshDatabase;

    private function purchasableVariant(int $stock = 10): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => $stock]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 25]);

        return $variant;
    }

    /**
     * @return list<int>
     */
    private function acceptAllCurrentLegalDocuments(): array
    {
        return collect(LegalDocumentType::requiredAtCheckout())
            ->map(fn (LegalDocumentType $type) => LegalDocument::factory()->create(['type' => $type, 'version' => '1.0'])->id)
            ->values()
            ->all();
    }

    #[Test]
    public function placing_an_order_succeeds_even_when_the_confirmation_email_fails_to_send(): void
    {
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new RuntimeException('Connection could not be established with host "127.0.0.1:1025"'));

        $variant = $this->purchasableVariant();
        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $guestToken = $addToCart->json('meta.guest_token');

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson('/api/v1/checkout/orders', [
            'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456'],
            'address' => ['country' => 'Bulgaria', 'city' => 'Sofia', 'postal_code' => '1000', 'address_line' => 'ul. Vitosha 1'],
            'shipping_carrier' => 'box_now',
            'shipping_delivery_type' => 'locker',
            'shipping_office_id' => 'locker-1',
            'shipping_office_name' => 'BOX NOW Sofia Center',
            'shipping_office_city' => 'Sofia',
            'shipping_office_address' => 'bul. Sofia 1',
            'billing_same_as_shipping' => false,
            'billing_address' => ['country' => 'Bulgaria', 'city' => 'Sofia', 'postal_code' => '1000', 'address_line' => 'ul. Vitosha 1'],
            'legal_document_ids' => $this->acceptAllCurrentLegalDocuments(),
        ]);

        $response->assertCreated();
        // awaiting_payment, not pending: placeOrder() also initiates the
        // card payment in the same request, which advances the status one
        // step further than the order's initial creation state.
        $this->assertDatabaseHas('orders', ['id' => $response->json('data.id'), 'status' => OrderStatus::AwaitingPayment->value]);
    }

    #[Test]
    public function an_admin_status_transition_persists_even_when_its_email_fails_to_send(): void
    {
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new RuntimeException('Connection could not be established with host "127.0.0.1:1025"'));

        $order = Order::factory()->create(['status' => OrderStatus::Paid]);
        OrderItem::factory()->for($order)->create();

        $updated = $this->app->make(OrderStatusService::class)->transitionTo($order, OrderStatus::Cancelled, changedBy: null);

        $this->assertSame(OrderStatus::Cancelled, $updated->status);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Cancelled->value]);
    }
}
