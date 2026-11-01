<?php

namespace Tests\Feature\Reviews;

use App\Enums\Currency;
use App\Enums\OrderStatus;
use App\Enums\VariantStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use App\Services\KitShellProductService;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A kit order line carries the kit's catalog shell variant (see
 * KitShellProductService / OrderService::placeOrder()), which is all the
 * unchanged review system needs to let a kit purchase be reviewed - and
 * both kit pages can pool their reviews by passing both shells' variant
 * ids.
 */
class KitReviewTest extends TestCase
{
    use RefreshDatabase;

    private function kit(string $slug, string $name, float $amount): ProductBundle
    {
        $component = ProductVariant::factory()->for(Product::factory()->published())->create(['status' => VariantStatus::Active]);
        $component->inventory()->create(['quantity_on_hand' => 20]);
        $component->prices()->create(['currency' => Currency::EUR->value, 'amount' => 5]);

        $bundle = ProductBundle::create([
            'slug' => $slug,
            'name' => $name,
            'currency' => Currency::EUR->value,
            'amount' => $amount,
            'status' => VariantStatus::Active->value,
        ]);
        $bundle->items()->create(['product_variant_id' => $component->id, 'quantity' => 1]);

        app(KitShellProductService::class)->sync($bundle);

        return $bundle->fresh();
    }

    /**
     * Shaped exactly like the line OrderService::placeOrder() writes for a
     * kit (see OrderBundleStockTest for placeOrder() itself).
     */
    private function deliveredKitOrder(User $customer, ProductBundle $bundle): Order
    {
        $order = Order::factory()->forUser($customer)->create(['status' => OrderStatus::Delivered]);
        OrderItem::factory()->for($order)->create([
            'product_variant_id' => $bundle->shell_product_variant_id,
            'bundle_id' => $bundle->id,
            'bundle_name' => $bundle->name,
            'product_name' => $bundle->name,
            'variant_name' => null,
            'sku' => $bundle->slug,
        ]);

        return $order;
    }

    private function review(User $customer, Order $order, ProductBundle $bundle, int $rating): void
    {
        $this->actingAs($customer)->postJson('/api/v1/customer/reviews', [
            'order_id' => $order->id,
            'product_variant_id' => $bundle->shell_product_variant_id,
            'rating' => $rating,
            'title' => 'Great kit',
            'body' => 'Everything in one box, very happy with it.',
        ])->assertCreated()->assertJsonPath('data.verified_purchase', true);
    }

    #[Test]
    public function a_customer_who_bought_a_kit_can_review_it_through_the_normal_flow(): void
    {
        $onePerson = $this->kit('miswak-1-person-kit', '1-Person Kit', 29.99);
        $customer = User::factory()->create();
        $order = $this->deliveredKitOrder($customer, $onePerson);

        // The 30-day reminder now finds the kit as a reviewable product.
        $shellProduct = ProductVariant::findOrFail($onePerson->shell_product_variant_id)->product;
        $this->assertTrue(app(ReviewService::class)->reviewableProductForOrder($order->load('items.productVariant.product'))?->is($shellProduct));

        $this->review($customer, $order, $onePerson, 5);

        $review = Review::sole();
        $this->assertSame($shellProduct->id, $review->product_id);
        $this->assertSame($onePerson->shell_product_variant_id, $review->product_variant_id);

        // Same one-review-per-order+product rule as any other product.
        $this->actingAs($customer)->postJson('/api/v1/customer/reviews', [
            'order_id' => $order->id,
            'product_variant_id' => $onePerson->shell_product_variant_id,
            'rating' => 4,
            'title' => 'Again',
            'body' => 'Trying to review the same kit twice.',
        ])->assertUnprocessable();
    }

    #[Test]
    public function both_kit_pages_return_the_same_combined_reviews_when_given_both_shell_variants(): void
    {
        $onePerson = $this->kit('miswak-1-person-kit', '1-Person Kit', 29.99);
        $twoPerson = $this->kit('miswak-2-person-kit', '2-Person Kit', 54.99);

        $first = User::factory()->create();
        $this->review($first, $this->deliveredKitOrder($first, $onePerson), $onePerson, 5);
        $second = User::factory()->create();
        $this->review($second, $this->deliveredKitOrder($second, $twoPerson), $twoPerson, 3);

        // An unrelated product's review never leaks into the kit pool.
        $other = ProductVariant::factory()->for(Product::factory()->published())->create();
        $third = User::factory()->create();
        $otherOrder = Order::factory()->forUser($third)->create(['status' => OrderStatus::Delivered]);
        OrderItem::factory()->for($otherOrder)->for($other, 'productVariant')->create();
        $this->actingAs($third)->postJson('/api/v1/customer/reviews', [
            'order_id' => $otherOrder->id,
            'product_variant_id' => $other->id,
            'rating' => 1,
            'title' => 'Other',
            'body' => 'A review of some other product entirely.',
        ])->assertCreated();

        $query = http_build_query(['product_variant_ids' => [$onePerson->shell_product_variant_id, $twoPerson->shell_product_variant_id]]);

        $ids = fn (string $slug) => collect($this->getJson("/api/v1/products/{$slug}/reviews?{$query}")->assertOk()->json('data'))
            ->pluck('id')->sort()->values()->all();

        $this->assertCount(2, $ids('miswak-1-person-kit'));
        $this->assertSame($ids('miswak-1-person-kit'), $ids('miswak-2-person-kit'));

        foreach (['miswak-1-person-kit', 'miswak-2-person-kit'] as $slug) {
            $this->getJson("/api/v1/products/{$slug}/reviews/summary?{$query}")
                ->assertOk()
                ->assertJsonPath('data.review_count', 2)
                ->assertJsonPath('data.average_rating', 4);
        }

        // Without a variant filter, each kit page still shows only its own.
        $this->assertCount(1, $this->getJson('/api/v1/products/miswak-1-person-kit/reviews')->json('data'));
        $this->getJson('/api/v1/products/miswak-2-person-kit/reviews/summary')->assertJsonPath('data.review_count', 1);

        // An unknown slug still 404s even with a valid variant filter.
        $this->getJson("/api/v1/products/no-such-kit/reviews?{$query}")->assertNotFound();
    }
}
