<?php

namespace Tests\Feature\Checkout;

use App\Enums\Currency;
use App\Enums\LegalDocumentType;
use App\Enums\VariantStatus;
use App\Models\LegalDocument;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function purchasableVariant(int $stock = 10): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => $stock]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 18]);

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

    #[Test]
    public function billing_address_defaults_to_the_shipping_address_when_marked_the_same(): void
    {
        $variant = $this->purchasableVariant();
        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $guestToken = $addToCart->json('meta.guest_token');

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson('/api/v1/checkout/orders', [
            'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456'],
            'address' => ['country' => 'Bulgaria', 'city' => 'Sofia', 'postal_code' => '1000', 'address_line' => 'ul. Vitosha 1'],
            'billing_same_as_shipping' => true,
            'shipping_carrier' => 'econt',
            'shipping_delivery_type' => 'address',
            'legal_document_ids' => $this->acceptAllCurrentLegalDocuments(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.billing_same_as_shipping', true);
        $response->assertJsonPath('data.billing_address.city', 'Sofia');
        $response->assertJsonPath('data.billing_address.address_line', 'ul. Vitosha 1');

        $this->assertDatabaseHas('orders', [
            'order_number' => $response->json('data.order_number'),
            'billing_city' => 'Sofia',
            'billing_address_line' => 'ul. Vitosha 1',
        ]);
    }

    #[Test]
    public function a_distinct_billing_address_is_stored_when_provided(): void
    {
        $variant = $this->purchasableVariant();
        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $guestToken = $addToCart->json('meta.guest_token');

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson('/api/v1/checkout/orders', [
            'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456'],
            'address' => ['country' => 'Bulgaria', 'city' => 'Sofia', 'postal_code' => '1000', 'address_line' => 'ul. Vitosha 1'],
            'billing_same_as_shipping' => false,
            'billing_address' => ['country' => 'Bulgaria', 'city' => 'Plovdiv', 'postal_code' => '4000', 'address_line' => 'ul. Glavna 9'],
            'shipping_carrier' => 'econt',
            'shipping_delivery_type' => 'address',
            'legal_document_ids' => $this->acceptAllCurrentLegalDocuments(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.billing_same_as_shipping', false);
        $response->assertJsonPath('data.billing_address.city', 'Plovdiv');
        $response->assertJsonPath('data.address.city', 'Sofia');
    }

    #[Test]
    public function an_active_promotion_and_discount_are_snapshotted_onto_the_order_item(): void
    {
        $variant = $this->purchasableVariant();
        $variant->prices()->update(['compare_at_amount' => 24]); // amount stays 18 — a 6 EUR markdown
        $promotion = Promotion::factory()->create(['name' => 'Summer Sale']);
        $promotion->products()->attach($variant->product);

        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 2]);
        $guestToken = $addToCart->json('meta.guest_token');

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson('/api/v1/checkout/orders', [
            'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456'],
            'address' => ['country' => 'Bulgaria', 'city' => 'Sofia', 'postal_code' => '1000', 'address_line' => 'ul. Vitosha 1'],
            'shipping_carrier' => 'econt',
            'shipping_delivery_type' => 'address',
            'legal_document_ids' => $this->acceptAllCurrentLegalDocuments(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.items.0.promotion_name', 'Summer Sale');
        $response->assertJsonPath('data.items.0.compare_at_price', 24);
        $response->assertJsonPath('data.items.0.discount_amount', 12); // (24-18) * 2
        $response->assertJsonPath('data.totals.discount_total', 12);

        // The promotion is later deactivated/deleted — the order must not care.
        $promotion->delete();
        $variant->prices()->update(['compare_at_amount' => null]);

        $reloaded = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->getJson("/api/v1/orders/{$response->json('data.id')}?token={$response->json('meta.guest_access_token')}");

        $reloaded->assertJsonPath('data.items.0.promotion_name', 'Summer Sale');
        $reloaded->assertJsonPath('data.items.0.discount_amount', 12);
    }

    #[Test]
    public function snapshot_survives_the_variant_being_deleted_after_purchase(): void
    {
        $variant = $this->purchasableVariant();
        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $guestToken = $addToCart->json('meta.guest_token');

        $placed = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson('/api/v1/checkout/orders', [
            'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456'],
            'address' => ['country' => 'Bulgaria', 'city' => 'Sofia', 'postal_code' => '1000', 'address_line' => 'ul. Vitosha 1'],
            'shipping_carrier' => 'econt',
            'shipping_delivery_type' => 'address',
            'legal_document_ids' => $this->acceptAllCurrentLegalDocuments(),
        ]);

        $variant->delete();

        $reloaded = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->getJson("/api/v1/orders/{$placed->json('data.id')}?token={$placed->json('meta.guest_access_token')}");

        $reloaded->assertOk();
        $reloaded->assertJsonCount(1, 'data.items');
        $this->assertNotNull($reloaded->json('data.items.0.product_name'));
    }
}
