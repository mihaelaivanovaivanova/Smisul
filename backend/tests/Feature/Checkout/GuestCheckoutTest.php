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

class GuestCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function purchasableVariant(int $stock = 10): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => $stock]);
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
            'shipping_carrier' => 'econt',
            'legal_document_ids' => $overrides['legal_document_ids'] ?? $this->acceptAllCurrentLegalDocuments(),
        ], $overrides);
    }

    #[Test]
    public function shipping_methods_are_publicly_listed(): void
    {
        $response = $this->getJson('/api/v1/checkout/shipping-methods');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonFragment(['carrier' => 'econt']);
        $response->assertJsonFragment(['carrier' => 'speedy']);
        $response->assertJsonFragment(['carrier' => 'box_now']);
    }

    #[Test]
    public function current_legal_documents_are_publicly_listed(): void
    {
        $this->acceptAllCurrentLegalDocuments();

        $response = $this->getJson('/api/v1/checkout/legal-documents');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    }

    #[Test]
    public function a_guest_can_place_an_order_from_their_cart(): void
    {
        $variant = $this->purchasableVariant(10);

        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 2]);
        $guestToken = $addToCart->json('meta.guest_token');

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->postJson('/api/v1/checkout/orders', $this->validPayload());

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.customer.first_name', 'Ivan');
        $response->assertJsonPath('data.shipping.carrier', 'econt');
        $response->assertJsonPath('data.shipping.price', 5.99);
        $response->assertJsonCount(1, 'data.items');
        $response->assertJsonPath('data.items.0.quantity', 2);
        $response->assertJsonPath('data.items.0.unit_price', 19.99);
        $response->assertJsonPath('data.items.0.line_total', 39.98);
        $response->assertJsonPath('data.totals.subtotal', 39.98);
        $response->assertJsonPath('data.totals.grand_total', 45.97);
        $this->assertNotNull($response->json('meta.guest_access_token'));

        $this->assertDatabaseHas('orders', ['order_number' => $response->json('data.order_number'), 'user_id' => null]);
    }

    #[Test]
    public function placing_an_order_empties_the_cart_but_keeps_stock_held_pending_payment(): void
    {
        $variant = $this->purchasableVariant(10);
        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 3]);
        $guestToken = $addToCart->json('meta.guest_token');

        $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->postJson('/api/v1/checkout/orders', $this->validPayload())
            ->assertCreated();

        $cart = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->getJson('/api/v1/cart');
        $cart->assertJsonPath('data.items', []);

        // No payment gateway exists yet — placing the order must not commit
        // stock. The 3 units stay reserved (held) until OrderService's
        // confirmPayment()/cancel() seam resolves them (see its docblock).
        $variant->inventory->refresh();
        $this->assertSame(10, $variant->inventory->quantity_on_hand);
        $this->assertSame(3, $variant->inventory->quantity_reserved);
    }

    #[Test]
    public function a_guest_can_view_their_order_confirmation_with_the_access_token(): void
    {
        $variant = $this->purchasableVariant(5);
        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $guestToken = $addToCart->json('meta.guest_token');

        $placed = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->postJson('/api/v1/checkout/orders', $this->validPayload());

        $orderId = $placed->json('data.id');
        $accessToken = $placed->json('meta.guest_access_token');

        $this->getJson("/api/v1/orders/{$orderId}?token={$accessToken}")
            ->assertOk()
            ->assertJsonPath('data.id', $orderId);

        $this->getJson("/api/v1/orders/{$orderId}?token=wrong-token")->assertForbidden();
        $this->getJson("/api/v1/orders/{$orderId}")->assertForbidden();
    }
}
