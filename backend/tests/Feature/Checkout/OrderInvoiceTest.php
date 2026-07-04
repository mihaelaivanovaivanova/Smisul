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

class OrderInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function purchasableVariant(int $stock = 10): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => $stock]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 10]);

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

    private function placeOrder(?User $user = null): array
    {
        $variant = $this->purchasableVariant();
        $request = $user ? $this->actingAs($user) : $this;
        $addToCart = $request->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $guestToken = $addToCart->json('meta.guest_token');

        $request = $user ? $this->actingAs($user) : $this->withHeaders(['X-Guest-Cart-Token' => $guestToken]);
        $placed = $request->postJson('/api/v1/checkout/orders', [
            'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456'],
            'address' => ['country' => 'Bulgaria', 'city' => 'Sofia', 'postal_code' => '1000', 'address_line' => 'ul. Vitosha 1'],
            'shipping_carrier' => 'econt',
            'shipping_delivery_type' => 'address',
            'legal_document_ids' => $this->acceptAllCurrentLegalDocuments(),
        ]);

        return [$placed, $guestToken];
    }

    #[Test]
    public function a_guest_can_download_their_orders_invoice_with_the_access_token(): void
    {
        [$placed] = $this->placeOrder();
        $orderId = $placed->json('data.id');
        $token = $placed->json('meta.guest_access_token');

        $response = $this->get("/api/v1/orders/{$orderId}/invoice?token={$token}");

        $response->assertOk();
        $response->assertHeader('Content-Disposition');
        $response->assertSee('Фактура', escape: false);
        $response->assertSee($placed->json('data.order_number'), escape: false);
    }

    #[Test]
    public function a_guest_cannot_download_an_invoice_without_the_correct_token(): void
    {
        [$placed] = $this->placeOrder();
        $orderId = $placed->json('data.id');

        $this->get("/api/v1/orders/{$orderId}/invoice")->assertForbidden();
        $this->get("/api/v1/orders/{$orderId}/invoice?token=wrong")->assertForbidden();
    }

    #[Test]
    public function a_registered_customer_can_download_their_own_invoice(): void
    {
        $user = User::factory()->create();
        [$placed] = $this->placeOrder($user);

        $this->actingAs($user)->get("/api/v1/orders/{$placed->json('data.id')}/invoice")
            ->assertOk()
            ->assertHeader('Content-Disposition');
    }
}
