<?php

namespace Tests\Feature\Checkout;

use App\Enums\Currency;
use App\Enums\LegalDocumentType;
use App\Enums\VariantStatus;
use App\Models\LegalDocument;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;

    private function purchasableVariant(int $stock = 10): ProductVariant
    {
        $product = Product::factory()->published()->create(['name' => 'Original Product Name']);
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active, 'name' => '150g']);
        $variant->inventory()->create(['quantity_on_hand' => $stock]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 25]);

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

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'customer' => [
                'first_name' => 'Ivan',
                'last_name' => 'Ivanov',
                'email' => 'ivan@example.com',
                'phone' => '+359888123456',
            ],
            'address' => [
                'country' => 'Bulgaria',
                'city' => 'Sofia',
                'postal_code' => '1000',
                'address_line' => 'ul. Vitosha 1',
            ],
            'shipping_carrier' => 'box_now',
            'legal_document_ids' => $overrides['legal_document_ids'] ?? $this->acceptAllCurrentLegalDocuments(),
        ], $overrides);
    }

    #[Test]
    public function the_order_number_follows_the_expected_format(): void
    {
        $variant = $this->purchasableVariant();
        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $guestToken = $addToCart->json('meta.guest_token');

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->postJson('/api/v1/checkout/orders', $this->validPayload());

        $this->assertMatchesRegularExpression('/^SM-\d{6}-[A-Z0-9]{6}$/', $response->json('data.order_number'));
    }

    #[Test]
    public function the_order_snapshots_product_details_and_survives_later_product_changes(): void
    {
        $variant = $this->purchasableVariant();
        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $guestToken = $addToCart->json('meta.guest_token');

        $placed = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->postJson('/api/v1/checkout/orders', $this->validPayload());

        $orderId = $placed->json('data.id');
        $accessToken = $placed->json('meta.guest_access_token');

        // The product is renamed, repriced, and finally deleted entirely.
        $variant->product->update(['name' => 'Renamed Product']);
        $variant->prices()->update(['amount' => 999]);
        $variant->delete();

        $reloaded = $this->getJson("/api/v1/orders/{$orderId}?token={$accessToken}");

        $reloaded->assertOk();
        $reloaded->assertJsonPath('data.items.0.product_name', 'Original Product Name');
        $reloaded->assertJsonPath('data.items.0.variant_name', '150g');
        $reloaded->assertJsonPath('data.items.0.unit_price', 25);
        $reloaded->assertJsonPath('data.items.0.line_total', 25);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_name' => 'Original Product Name',
            'unit_price' => 25,
        ]);
    }

    #[Test]
    public function legal_document_acceptances_are_recorded_with_timestamps(): void
    {
        $variant = $this->purchasableVariant();
        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $guestToken = $addToCart->json('meta.guest_token');

        $placed = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->postJson('/api/v1/checkout/orders', $this->validPayload());

        $orderId = $placed->json('data.id');
        $accessToken = $placed->json('meta.guest_access_token');

        $reloaded = $this->getJson("/api/v1/orders/{$orderId}?token={$accessToken}");

        $reloaded->assertJsonCount(5, 'data.legal_acceptances');
        $this->assertNotNull($reloaded->json('data.legal_acceptances.0.accepted_at'));

        $this->assertDatabaseCount('order_legal_acceptances', 5);
    }
}
