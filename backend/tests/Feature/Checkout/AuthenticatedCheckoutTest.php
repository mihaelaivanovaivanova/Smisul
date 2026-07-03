<?php

namespace Tests\Feature\Checkout;

use App\Enums\Currency;
use App\Enums\LegalDocumentType;
use App\Enums\VariantStatus;
use App\Models\LegalDocument;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthenticatedCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function purchasableVariant(int $stock = 10): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => $stock]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 9.5]);

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

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'customer' => [
                'first_name' => 'Maria',
                'last_name' => 'Petrova',
                'email' => 'maria@example.com',
                'phone' => '+359888000000',
            ],
            'address' => [
                'country' => 'Bulgaria',
                'city' => 'Plovdiv',
                'postal_code' => '4000',
                'address_line' => 'ul. Glavna 5',
            ],
            'shipping_carrier' => 'speedy',
            'legal_document_ids' => $overrides['legal_document_ids'] ?? $this->acceptAllCurrentLegalDocuments(),
        ], $overrides);
    }

    #[Test]
    public function a_registered_customer_can_place_an_order_and_it_is_linked_to_their_account(): void
    {
        $user = User::factory()->create();
        $variant = $this->purchasableVariant();

        $this->actingAs($user)->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);

        $response = $this->actingAs($user)->postJson('/api/v1/checkout/orders', $this->validPayload());

        $response->assertCreated();
        $this->assertNull($response->json('meta.guest_access_token'));
        $this->assertDatabaseHas('orders', [
            'order_number' => $response->json('data.order_number'),
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function a_registered_customer_can_view_their_own_order(): void
    {
        $user = User::factory()->create();
        $variant = $this->purchasableVariant();
        $this->actingAs($user)->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $placed = $this->actingAs($user)->postJson('/api/v1/checkout/orders', $this->validPayload());

        $this->actingAs($user)->getJson("/api/v1/orders/{$placed->json('data.id')}")
            ->assertOk()
            ->assertJsonPath('data.id', $placed->json('data.id'));
    }

    #[Test]
    public function a_registered_customer_cannot_view_another_customers_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $variant = $this->purchasableVariant();
        $this->actingAs($owner)->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $placed = $this->actingAs($owner)->postJson('/api/v1/checkout/orders', $this->validPayload());

        $this->actingAs($intruder)->getJson("/api/v1/orders/{$placed->json('data.id')}")
            ->assertForbidden();
    }
}
