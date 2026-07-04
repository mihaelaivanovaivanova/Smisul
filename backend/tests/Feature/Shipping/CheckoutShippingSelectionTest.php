<?php

namespace Tests\Feature\Shipping;

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
 * Checkout-side integration for the Sprint 8 delivery-type/office
 * selection — complements ShippingProviderAbstractionTest (which covers the
 * service layer directly) by exercising the real /checkout/orders endpoint
 * end to end.
 */
class CheckoutShippingSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function purchasableVariant(): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 15]);

        return $variant;
    }

    /**
     * @return list<int>
     */
    private function acceptAllCurrentLegalDocuments(): array
    {
        return collect(LegalDocumentType::cases())
            ->map(fn (LegalDocumentType $type) => LegalDocument::factory()->create(['type' => $type, 'version' => '1.0'])->id)
            ->values()
            ->all();
    }

    private function addToCartAndGetToken(): string
    {
        $variant = $this->purchasableVariant();
        $response = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);

        return $response->json('meta.guest_token');
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456'],
            'address' => ['country' => 'Bulgaria', 'city' => 'Sofia', 'postal_code' => '1000', 'address_line' => 'ul. Vitosha 1'],
            'legal_document_ids' => $this->acceptAllCurrentLegalDocuments(),
        ], $overrides);
    }

    #[Test]
    public function selecting_an_office_delivery_snapshots_the_office_onto_the_order(): void
    {
        $guestToken = $this->addToCartAndGetToken();

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson(
            '/api/v1/checkout/orders',
            $this->basePayload([
                'shipping_carrier' => 'econt',
                'shipping_delivery_type' => 'office',
                'shipping_office_id' => 'office-42',
                'shipping_office_name' => 'Econt Sofia Center',
            ]),
        );

        $response->assertCreated();
        $response->assertJsonPath('data.shipping.delivery_type', 'office');
        $response->assertJsonPath('data.shipping.office_id', 'office-42');
        $response->assertJsonPath('data.shipping.office_name', 'Econt Sofia Center');

        $order = Order::findOrFail($response->json('data.id'));
        $this->assertSame('office-42', $order->shipping_office_id);
    }

    #[Test]
    public function selecting_address_delivery_does_not_require_an_office(): void
    {
        $guestToken = $this->addToCartAndGetToken();

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson(
            '/api/v1/checkout/orders',
            $this->basePayload(['shipping_carrier' => 'econt', 'shipping_delivery_type' => 'address']),
        );

        $response->assertCreated();
        $response->assertJsonPath('data.shipping.delivery_type', 'address');
        $response->assertJsonPath('data.shipping.office_id', null);
    }

    #[Test]
    public function an_office_delivery_without_an_office_id_is_rejected(): void
    {
        $guestToken = $this->addToCartAndGetToken();

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson(
            '/api/v1/checkout/orders',
            $this->basePayload(['shipping_carrier' => 'econt', 'shipping_delivery_type' => 'office']),
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['shipping_office_id']);
    }

    #[Test]
    public function box_now_only_accepts_locker_delivery(): void
    {
        $guestToken = $this->addToCartAndGetToken();

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson(
            '/api/v1/checkout/orders',
            $this->basePayload(['shipping_carrier' => 'box_now', 'shipping_delivery_type' => 'address']),
        );

        $response->assertStatus(422);
    }

    #[Test]
    public function box_now_with_a_locker_selection_succeeds(): void
    {
        $guestToken = $this->addToCartAndGetToken();

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson(
            '/api/v1/checkout/orders',
            $this->basePayload([
                'shipping_carrier' => 'box_now',
                'shipping_delivery_type' => 'locker',
                'shipping_office_id' => 'locker-7',
                'shipping_office_name' => 'BOX NOW Mall of Sofia',
            ]),
        );

        $response->assertCreated();
        $response->assertJsonPath('data.shipping.carrier', 'box_now');
        $response->assertJsonPath('data.shipping.delivery_type', 'locker');
    }
}
