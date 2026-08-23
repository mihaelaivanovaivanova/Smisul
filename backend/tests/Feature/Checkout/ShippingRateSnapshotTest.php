<?php

namespace Tests\Feature\Checkout;

use App\Enums\Currency;
use App\Enums\LegalDocumentType;
use App\Enums\VariantStatus;
use App\Models\LegalDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * чл. 54, ал. 2 ЗЗП caps a withdrawal refund's delivery-cost component at
 * the cheapest standard delivery option offered AT THE TIME OF THAT ORDER
 * - which can differ from today's cheapest rate once prices/promos change
 * (see BoxNowShippingProvider's launch-promo window). These tests confirm
 * the snapshot captured at placement survives that kind of drift.
 */
class ShippingRateSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function purchasableVariant(): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 19.99]);

        return $variant;
    }

    /**
     * @return list<int>
     */
    private function acceptAllCurrentLegalDocuments(): array
    {
        return collect(LegalDocumentType::cases())
            ->map(function (LegalDocumentType $type) {
                $existing = LegalDocument::where('type', $type)->where('version', '1.0')->first();

                return $existing?->id ?? LegalDocument::factory()->create(['type' => $type, 'version' => '1.0'])->id;
            })
            ->values()
            ->all();
    }

    private function placeOrder(array $overrides = []): Order
    {
        $variant = $this->purchasableVariant();
        $cart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);

        $placed = $this->withHeaders(['X-Guest-Cart-Token' => $cart->json('meta.guest_token')])
            ->postJson('/api/v1/checkout/orders', array_merge([
                'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456'],
                'address' => ['country' => 'Bulgaria', 'city' => 'Sofia', 'postal_code' => '1000', 'address_line' => 'ul. Vitosha 1'],
                'shipping_carrier' => 'speedy',
                'shipping_delivery_type' => 'address',
                'legal_document_ids' => $this->acceptAllCurrentLegalDocuments(),
            ], $overrides))
            ->assertCreated();

        return Order::findOrFail($placed->json('data.id'));
    }

    #[Test]
    public function every_order_captures_a_snapshot_of_all_shipping_options_offered_at_placement(): void
    {
        $order = $this->placeOrder();

        $this->assertNotNull($order->shipping_rate_snapshot);
        $carriers = array_column($order->shipping_rate_snapshot, 'carrier');

        // Not just the chosen carrier (speedy) - every option shown at
        // checkout, since чл. 54, ал. 2 ЗЗП cares about the cheapest
        // available option, not the one actually picked.
        $this->assertContains('speedy', $carriers);
        $this->assertContains('box_now', $carriers);
    }

    #[Test]
    public function the_cheapest_option_is_computed_from_the_snapshot(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-15'));

        $order = $this->placeOrder();

        // BOX NOW's launch promo is active on this date - free.
        $this->assertSame(0.0, $order->cheapestStandardShippingPriceAtPlacement());
    }

    #[Test]
    public function the_snapshot_preserves_a_promo_price_even_after_the_promo_ends(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-15'));
        $order = $this->placeOrder();

        // Move past the promo window - BOX NOW is no longer free "today".
        $this->travelTo(\Carbon\Carbon::parse('2026-10-15'));

        // The order's own record of what was cheapest AT PLACEMENT must
        // stay 0.0 - recomputing against today's live prices would wrongly
        // inflate the refund cap for a withdrawal on this order.
        $this->assertSame(0.0, $order->fresh()->cheapestStandardShippingPriceAtPlacement());
    }

    #[Test]
    public function orders_placed_before_the_promo_or_after_it_ends_reflect_the_flat_rate_instead(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-15'));

        $order = $this->placeOrder();

        $this->assertGreaterThan(0.0, $order->cheapestStandardShippingPriceAtPlacement());
    }

    #[Test]
    public function orders_placed_before_this_feature_existed_return_null_rather_than_guessing(): void
    {
        $order = $this->placeOrder();
        $order->forceFill(['shipping_rate_snapshot' => null])->save();

        $this->assertNull($order->fresh()->cheapestStandardShippingPriceAtPlacement());
    }
}
