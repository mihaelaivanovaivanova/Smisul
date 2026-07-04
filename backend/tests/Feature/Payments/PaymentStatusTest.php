<?php

namespace Tests\Feature\Payments;

use App\Enums\Currency;
use App\Enums\LegalDocumentType;
use App\Enums\VariantStatus;
use App\Models\LegalDocument;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentStatusTest extends TestCase
{
    use RefreshDatabase;

    private function purchasableVariant(int $stock = 10): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => $stock]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 15]);

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

    private function placeOrder(?User $user = null): TestResponse
    {
        $variant = $this->purchasableVariant();
        $requester = $user ? $this->actingAs($user) : $this;
        $addToCart = $requester->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $guestToken = $addToCart->json('meta.guest_token');

        $requester = $user ? $this->actingAs($user) : $this->withHeaders(['X-Guest-Cart-Token' => $guestToken]);

        return $requester->postJson('/api/v1/checkout/orders', [
            'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456'],
            'address' => ['country' => 'Bulgaria', 'city' => 'Sofia', 'postal_code' => '1000', 'address_line' => 'ul. Vitosha 1'],
            'shipping_carrier' => 'econt',
            'shipping_delivery_type' => 'address',
            'legal_document_ids' => $this->acceptAllCurrentLegalDocuments(),
        ]);
    }

    #[Test]
    public function a_guest_can_poll_payment_status_with_the_access_token(): void
    {
        $placed = $this->placeOrder();
        $orderId = $placed->json('data.id');
        $token = $placed->json('meta.guest_access_token');

        $response = $this->getJson("/api/v1/payments/{$orderId}/status?token={$token}");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'initiated');
    }

    #[Test]
    public function a_guest_cannot_poll_status_without_the_correct_token(): void
    {
        $placed = $this->placeOrder();
        $orderId = $placed->json('data.id');

        $this->getJson("/api/v1/payments/{$orderId}/status")->assertForbidden();
        $this->getJson("/api/v1/payments/{$orderId}/status?token=wrong")->assertForbidden();
    }

    #[Test]
    public function a_registered_customer_can_poll_their_own_payment_status(): void
    {
        $user = User::factory()->create();
        $placed = $this->placeOrder($user);

        $this->actingAs($user)->getJson("/api/v1/payments/{$placed->json('data.id')}/status")
            ->assertOk()
            ->assertJsonPath('data.status', 'initiated');
    }

    #[Test]
    public function another_customer_cannot_poll_someone_elses_payment_status(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $placed = $this->placeOrder($owner);

        $this->actingAs($intruder)->getJson("/api/v1/payments/{$placed->json('data.id')}/status")
            ->assertForbidden();
    }
}
