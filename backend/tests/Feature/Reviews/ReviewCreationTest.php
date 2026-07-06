<?php

namespace Tests\Feature\Reviews;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReviewCreationTest extends TestCase
{
    use RefreshDatabase;

    private function deliveredOrderWithItem(User $user, ProductVariant $variant): Order
    {
        $order = Order::factory()->forUser($user)->create(['status' => OrderStatus::Delivered]);
        OrderItem::factory()->for($order)->for($variant, 'productVariant')->create();

        return $order;
    }

    private function validPayload(Order $order, ProductVariant $variant): array
    {
        return [
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'rating' => 5,
            'title' => 'Great product',
            'body' => 'Really happy with this purchase, would buy again.',
        ];
    }

    #[Test]
    public function a_guest_cannot_create_a_review(): void
    {
        $variant = ProductVariant::factory()->create();
        $order = Order::factory()->create(['status' => OrderStatus::Delivered]);
        OrderItem::factory()->for($order)->for($variant, 'productVariant')->create();

        $this->postJson('/api/v1/customer/reviews', $this->validPayload($order, $variant))
            ->assertUnauthorized();
    }

    #[Test]
    public function a_guest_cannot_list_or_delete_reviews(): void
    {
        $this->getJson('/api/v1/customer/reviews')->assertUnauthorized();
        $this->deleteJson('/api/v1/customer/reviews/1')->assertUnauthorized();
    }

    #[Test]
    public function an_administrator_cannot_review_as_a_customer(): void
    {
        $admin = User::factory()->administrator()->create();
        $variant = ProductVariant::factory()->create();
        $order = $this->deliveredOrderWithItem($admin, $variant);

        $this->actingAs($admin)->postJson('/api/v1/customer/reviews', $this->validPayload($order, $variant))
            ->assertForbidden();
    }

    #[Test]
    public function a_customer_can_review_a_product_from_a_delivered_order(): void
    {
        $customer = User::factory()->create();
        $variant = ProductVariant::factory()->create();
        $order = $this->deliveredOrderWithItem($customer, $variant);

        $response = $this->actingAs($customer)->postJson('/api/v1/customer/reviews', $this->validPayload($order, $variant));

        $response->assertCreated();
        $response->assertJsonPath('data.rating', 5);
        $response->assertJsonPath('data.verified_purchase', true);
        $response->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseHas('reviews', [
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'product_id' => $variant->product_id,
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function a_customer_cannot_review_a_product_they_did_not_purchase(): void
    {
        $customer = User::factory()->create();
        $purchasedVariant = ProductVariant::factory()->create();
        $unpurchasedVariant = ProductVariant::factory()->create();
        $order = $this->deliveredOrderWithItem($customer, $purchasedVariant);

        $this->actingAs($customer)
            ->postJson('/api/v1/customer/reviews', $this->validPayload($order, $unpurchasedVariant))
            ->assertUnprocessable();
    }

    #[Test]
    public function a_customer_cannot_review_someone_elses_order(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $variant = ProductVariant::factory()->create();
        $othersOrder = $this->deliveredOrderWithItem($otherCustomer, $variant);

        $this->actingAs($customer)
            ->postJson('/api/v1/customer/reviews', $this->validPayload($othersOrder, $variant))
            ->assertUnprocessable();
    }

    #[Test]
    public function a_customer_cannot_review_a_product_from_an_order_that_has_not_been_delivered(): void
    {
        $customer = User::factory()->create();
        $variant = ProductVariant::factory()->create();
        $order = Order::factory()->forUser($customer)->create(['status' => OrderStatus::Shipped]);
        OrderItem::factory()->for($order)->for($variant, 'productVariant')->create();

        $response = $this->actingAs($customer)
            ->postJson('/api/v1/customer/reviews', $this->validPayload($order, $variant));

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'You can only review products from an order that has been delivered.');
    }

    #[Test]
    public function a_customer_cannot_review_the_same_product_twice_for_the_same_order(): void
    {
        $customer = User::factory()->create();
        $variant = ProductVariant::factory()->create();
        $order = $this->deliveredOrderWithItem($customer, $variant);

        $this->actingAs($customer)->postJson('/api/v1/customer/reviews', $this->validPayload($order, $variant))
            ->assertCreated();

        $response = $this->actingAs($customer)->postJson('/api/v1/customer/reviews', $this->validPayload($order, $variant));

        $response->assertUnprocessable();
        $this->assertSame(1, Review::query()->where('order_id', $order->id)->count());
    }

    #[Test]
    public function a_customer_can_review_the_same_product_again_from_a_different_delivered_order(): void
    {
        $customer = User::factory()->create();
        $variant = ProductVariant::factory()->create();
        $firstOrder = $this->deliveredOrderWithItem($customer, $variant);
        $secondOrder = $this->deliveredOrderWithItem($customer, $variant);

        $this->actingAs($customer)->postJson('/api/v1/customer/reviews', $this->validPayload($firstOrder, $variant))
            ->assertCreated();

        $this->actingAs($customer)->postJson('/api/v1/customer/reviews', $this->validPayload($secondOrder, $variant))
            ->assertCreated();

        $this->assertSame(2, Review::query()->where('user_id', $customer->id)->count());
    }

    #[Test]
    public function creating_a_review_requires_a_rating_between_one_and_five(): void
    {
        $customer = User::factory()->create();
        $variant = ProductVariant::factory()->create();
        $order = $this->deliveredOrderWithItem($customer, $variant);

        $payload = $this->validPayload($order, $variant);
        $payload['rating'] = 6;

        $this->actingAs($customer)->postJson('/api/v1/customer/reviews', $payload)->assertUnprocessable();
    }
}
