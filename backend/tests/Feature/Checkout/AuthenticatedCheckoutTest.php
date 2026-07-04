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
            'shipping_delivery_type' => 'address',
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

    #[Test]
    public function a_registered_customer_can_list_their_own_order_history(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownVariant = $this->purchasableVariant();
        $this->actingAs($user)->postJson('/api/v1/cart/items', ['product_variant_id' => $ownVariant->id, 'quantity' => 1]);
        $ownOrder = $this->actingAs($user)->postJson('/api/v1/checkout/orders', $this->validPayload());

        $otherVariant = $this->purchasableVariant();
        $this->actingAs($otherUser)->postJson('/api/v1/cart/items', ['product_variant_id' => $otherVariant->id, 'quantity' => 1]);
        $this->actingAs($otherUser)->postJson('/api/v1/checkout/orders', $this->validPayload(['customer' => [
            'first_name' => 'Other',
            'last_name' => 'Customer',
            'email' => 'other@example.com',
            'phone' => '+359888999888',
        ]]));

        $response = $this->actingAs($user)->getJson('/api/v1/orders');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $ownOrder->json('data.id'));
    }

    #[Test]
    public function a_guest_cannot_list_order_history(): void
    {
        $this->getJson('/api/v1/orders')->assertUnauthorized();
    }
}
