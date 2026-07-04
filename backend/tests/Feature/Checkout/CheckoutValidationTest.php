<?php

namespace Tests\Feature\Checkout;

use App\Enums\Currency;
use App\Enums\LegalDocumentType;
use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Models\LegalDocument;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckoutValidationTest extends TestCase
{
    use RefreshDatabase;

    private function purchasableVariant(int $stock = 10): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => $stock]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 12]);

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
            'shipping_delivery_type' => 'address',
            'legal_document_ids' => $overrides['legal_document_ids'] ?? $this->acceptAllCurrentLegalDocuments(),
        ], $overrides);
    }

    #[Test]
    public function placing_an_order_with_an_empty_cart_is_rejected(): void
    {
        $this->postJson('/api/v1/checkout/orders', $this->validPayload())
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Your cart is empty — add something before checking out.']);
    }

    #[Test]
    public function missing_customer_and_address_fields_are_rejected(): void
    {
        $variant = $this->purchasableVariant();
        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $guestToken = $addToCart->json('meta.guest_token');

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->postJson('/api/v1/checkout/orders', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'customer.first_name',
            'customer.last_name',
            'customer.email',
            'customer.phone',
            'address.country',
            'address.city',
            'address.postal_code',
            'address.address_line',
            'shipping_carrier',
            'legal_document_ids',
        ]);
    }

    #[Test]
    public function an_invalid_shipping_carrier_is_rejected(): void
    {
        $variant = $this->purchasableVariant();
        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $guestToken = $addToCart->json('meta.guest_token');

        $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->postJson('/api/v1/checkout/orders', $this->validPayload(['shipping_carrier' => 'dhl']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_carrier']);
    }

    #[Test]
    public function missing_legal_document_acceptance_is_rejected(): void
    {
        $variant = $this->purchasableVariant();
        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $guestToken = $addToCart->json('meta.guest_token');

        // Only accept one of the five required documents.
        $oneDocument = LegalDocument::factory()->create(['type' => LegalDocumentType::TermsOfService, 'version' => '1.0']);

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->postJson('/api/v1/checkout/orders', $this->validPayload(['legal_document_ids' => [$oneDocument->id]]));

        $response->assertStatus(422);
        $this->assertNotEmpty($response->json('missing_types'));
    }

    #[Test]
    public function an_item_that_became_unavailable_after_being_added_is_rejected_at_placement(): void
    {
        $variant = $this->purchasableVariant(5);
        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 2]);
        $guestToken = $addToCart->json('meta.guest_token');

        // The product is unpublished after being added to the cart —
        // simulates an admin pulling it before the customer checks out.
        $variant->product->update(['status' => ProductStatus::Draft]);

        $response = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])
            ->postJson('/api/v1/checkout/orders', $this->validPayload());

        $response->assertStatus(422);
        $this->assertContains($variant->sku, $response->json('skus'));
    }
}
