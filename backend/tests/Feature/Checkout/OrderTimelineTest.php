<?php

namespace Tests\Feature\Checkout;

use App\Enums\Currency;
use App\Enums\LegalDocumentType;
use App\Enums\OrderStatus;
use App\Enums\VariantStatus;
use App\Models\LegalDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderTimelineTest extends TestCase
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

    #[Test]
    public function the_order_detail_response_includes_a_growing_timeline(): void
    {
        $variant = $this->purchasableVariant();
        $addToCart = $this->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $guestToken = $addToCart->json('meta.guest_token');

        $placed = $this->withHeaders(['X-Guest-Cart-Token' => $guestToken])->postJson('/api/v1/checkout/orders', [
            'customer' => ['first_name' => 'Ivan', 'last_name' => 'Ivanov', 'email' => 'ivan@example.com', 'phone' => '+359888123456'],
            'address' => ['country' => 'Bulgaria', 'city' => 'Sofia', 'postal_code' => '1000', 'address_line' => 'ul. Vitosha 1'],
            'shipping_carrier' => 'econt',
            'legal_document_ids' => $this->acceptAllCurrentLegalDocuments(),
        ]);

        $orderId = $placed->json('data.id');
        $token = $placed->json('meta.guest_access_token');

        $afterPlacement = $this->getJson("/api/v1/orders/{$orderId}?token={$token}");
        $afterPlacement->assertJsonCount(1, 'data.timeline');
        $afterPlacement->assertJsonPath('data.timeline.0.status', 'pending');
        $afterPlacement->assertJsonPath('data.timeline.0.previous_status', null);

        $admin = User::factory()->administrator()->create();
        $order = Order::findOrFail($orderId);
        app(OrderStatusService::class)->transitionTo($order, OrderStatus::AwaitingPayment, $admin, 'Sent to gateway');
        app(OrderStatusService::class)->transitionTo($order->fresh(), OrderStatus::Paid, $admin);

        $afterTransitions = $this->getJson("/api/v1/orders/{$orderId}?token={$token}");
        $afterTransitions->assertJsonCount(3, 'data.timeline');
        $afterTransitions->assertJsonPath('data.timeline.1.status', 'awaiting_payment');
        $afterTransitions->assertJsonPath('data.timeline.1.previous_status', 'pending');
        $afterTransitions->assertJsonPath('data.timeline.1.note', 'Sent to gateway');
        $afterTransitions->assertJsonPath('data.timeline.1.changed_by', $admin->fullName());
        $afterTransitions->assertJsonPath('data.timeline.2.status', 'paid');
    }
}
