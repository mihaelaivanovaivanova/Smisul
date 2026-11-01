<?php

namespace Tests\Feature\Order;

use App\Enums\Currency;
use App\Enums\LegalDocumentType;
use App\Enums\OrderStatus;
use App\Enums\VariantStatus;
use App\Http\Resources\OrderItemResource;
use App\Models\LegalDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductVariant;
use App\Services\KitShellProductService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A kit is ONE order line (its product_variant_id, when set, is only the
 * kit's catalog shell - see KitShellProductService) at the kit's
 * stored price, while confirm/cancel/delete commit or release stock on
 * every real component variant, scaled by the line's quantity - see
 * OrderService's bundle branches.
 */
class OrderBundleStockTest extends TestCase
{
    use RefreshDatabase;

    private function variant(float $amount, int $stock, int $packSize = 1): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->for($product)->packSize($packSize)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => $stock]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => $amount]);

        return $variant;
    }

    /**
     * 1-Person-Kit-shaped (1x 5-pack, 1x case, 1x scraper) unless told
     * otherwise - $caseQuantity/$scraperQuantity scale it to the 2-Person
     * shape.
     *
     * @return array{0: ProductBundle, 1: ProductVariant, 2: ProductVariant, 3: ProductVariant}
     */
    private function kit(int $caseQuantity = 1, int $scraperQuantity = 1): array
    {
        $pack = $this->variant(17.99, 20, 5);
        $case = $this->variant(6.49, 20);
        $scraper = $this->variant(6.99, 20);

        $bundle = ProductBundle::create([
            'slug' => 'test-1-person-kit',
            'name' => '1-Person Kit',
            'currency' => Currency::EUR->value,
            'amount' => 29.99,
            'status' => VariantStatus::Active->value,
        ]);
        $bundle->items()->create(['product_variant_id' => $pack->id, 'quantity' => 1]);
        $bundle->items()->create(['product_variant_id' => $case->id, 'quantity' => $caseQuantity]);
        $bundle->items()->create(['product_variant_id' => $scraper->id, 'quantity' => $scraperQuantity]);

        return [$bundle, $pack, $case, $scraper];
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

    /**
     * @param  list<array{0: string, 1: array<string, int>}>  $additions  [endpoint, payload] pairs
     */
    private function placeOrder(array $additions): Order
    {
        $token = null;

        foreach ($additions as [$endpoint, $payload]) {
            $request = $token !== null ? $this->withHeaders(['X-Guest-Cart-Token' => $token]) : $this;
            $response = $request->postJson($endpoint, $payload);
            $response->assertSuccessful();
            $token = $response->json('meta.guest_token');
        }

        $placed = $this->withHeaders(['X-Guest-Cart-Token' => $token])->postJson('/api/v1/checkout/orders', [
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
            'shipping_carrier' => 'speedy',
            'shipping_delivery_type' => 'address',
            'legal_document_ids' => $this->acceptAllCurrentLegalDocuments(),
        ]);

        $placed->assertCreated();

        return Order::findOrFail($placed->json('data.id'));
    }

    /**
     * @return array{0: int, 1: int} [on_hand, reserved]
     */
    private function stock(ProductVariant $variant): array
    {
        $inventory = $variant->inventory()->firstOrFail();

        return [$inventory->quantity_on_hand, $inventory->quantity_reserved];
    }

    #[Test]
    public function placing_an_order_snapshots_a_kit_as_one_line_at_its_stored_price(): void
    {
        [$bundle, $pack, $case, $scraper] = $this->kit();

        $order = $this->placeOrder([['/api/v1/cart/bundles', ['bundle_id' => $bundle->id, 'quantity' => 2]]]);

        $this->assertCount(1, $order->items);
        $item = $order->items->first();
        $this->assertNull($item->product_variant_id);
        $this->assertSame($bundle->id, $item->bundle_id);
        $this->assertSame('1-Person Kit', $item->bundle_name);
        $this->assertSame('1-Person Kit', $item->product_name);
        $this->assertSame('test-1-person-kit', $item->sku);
        $this->assertSame(2, $item->quantity);
        $this->assertSame('29.99', $item->unit_price);
        $this->assertSame('59.98', $item->line_total);
        // 17.99 + 6.49 + 6.99, live at order time - display only.
        $this->assertSame('31.47', $item->compare_at_price);
        $this->assertSame('0.00', $item->discount_amount);
        $this->assertSame('59.98', $order->subtotal);
        $this->assertSame('0.00', $order->discount_total);

        // Placement holds the reservation exactly as-is (nothing decremented).
        $this->assertSame([20, 2], $this->stock($pack));
        $this->assertSame([20, 2], $this->stock($case));
        $this->assertSame([20, 2], $this->stock($scraper));
    }

    #[Test]
    public function the_order_api_exposes_the_kit_snapshot(): void
    {
        [$bundle] = $this->kit();

        $order = $this->placeOrder([['/api/v1/cart/bundles', ['bundle_id' => $bundle->id]]]);

        $this->assertSame($bundle->id, $order->load('items')->items->first()->bundle_id);

        $resource = (new OrderItemResource($order->items->first()))->resolve();
        $this->assertSame('1-Person Kit', $resource['bundle_name']);
        $this->assertNull($resource['product_variant_id']);
        $this->assertNull($resource['product_slug']);
    }

    #[Test]
    public function confirming_payment_decrements_every_real_component_scaled_by_line_quantity(): void
    {
        [$bundle, $pack, $case, $scraper] = $this->kit(caseQuantity: 2, scraperQuantity: 2);

        $order = $this->placeOrder([['/api/v1/cart/bundles', ['bundle_id' => $bundle->id, 'quantity' => 3]]]);

        $confirmed = app(OrderService::class)->confirmPayment($order);

        $this->assertSame(OrderStatus::Paid, $confirmed->status);
        $this->assertSame([17, 0], $this->stock($pack));
        $this->assertSame([14, 0], $this->stock($case));
        $this->assertSame([14, 0], $this->stock($scraper));
    }

    #[Test]
    public function confirming_cash_on_delivery_commits_kit_and_ordinary_lines_alike(): void
    {
        [$bundle, $pack, $case, $scraper] = $this->kit();

        $order = $this->placeOrder([
            ['/api/v1/cart/bundles', ['bundle_id' => $bundle->id, 'quantity' => 1]],
            // A standalone case alongside the kit - same inventory row.
            ['/api/v1/cart/items', ['product_variant_id' => $case->id, 'quantity' => 2]],
        ]);

        $this->assertCount(2, $order->items);

        app(OrderService::class)->confirmCashOnDelivery($order);

        $this->assertSame([19, 0], $this->stock($pack));
        $this->assertSame([17, 0], $this->stock($case));
        $this->assertSame([19, 0], $this->stock($scraper));
    }

    #[Test]
    public function cancelling_a_pending_kit_order_releases_every_component_without_decrementing(): void
    {
        [$bundle, $pack, $case, $scraper] = $this->kit(caseQuantity: 2, scraperQuantity: 2);

        $order = $this->placeOrder([['/api/v1/cart/bundles', ['bundle_id' => $bundle->id, 'quantity' => 2]]]);
        $this->assertSame([20, 4], $this->stock($case));

        $cancelled = app(OrderService::class)->cancel($order);

        $this->assertSame(OrderStatus::Cancelled, $cancelled->status);
        $this->assertSame([20, 0], $this->stock($pack));
        $this->assertSame([20, 0], $this->stock($case));
        $this->assertSame([20, 0], $this->stock($scraper));
    }

    #[Test]
    public function deleting_a_pending_kit_order_releases_every_component(): void
    {
        [$bundle, $pack, $case, $scraper] = $this->kit();

        $order = $this->placeOrder([['/api/v1/cart/bundles', ['bundle_id' => $bundle->id, 'quantity' => 2]]]);

        app(OrderService::class)->delete($order);

        $this->assertSame([20, 0], $this->stock($pack));
        $this->assertSame([20, 0], $this->stock($case));
        $this->assertSame([20, 0], $this->stock($scraper));
        $this->assertNull(Order::find($order->id));
    }

    #[Test]
    public function cancelling_an_already_confirmed_kit_order_leaves_committed_stock_alone(): void
    {
        [$bundle, $pack] = $this->kit();

        $order = $this->placeOrder([['/api/v1/cart/bundles', ['bundle_id' => $bundle->id, 'quantity' => 2]]]);
        app(OrderService::class)->confirmPayment($order);

        app(OrderService::class)->cancel($order->fresh());

        $this->assertSame([18, 0], $this->stock($pack));
    }

    #[Test]
    public function a_kit_line_carries_its_shell_variant_but_only_real_components_are_ever_stock_processed(): void
    {
        [$bundle, $pack, $case, $scraper] = $this->kit(caseQuantity: 2, scraperQuantity: 2);
        $shell = app(KitShellProductService::class)->sync($bundle);

        $order = $this->placeOrder([['/api/v1/cart/bundles', ['bundle_id' => $bundle->id, 'quantity' => 2]]]);

        $item = $order->items->sole();
        $this->assertSame($shell->id, $item->product_variant_id);
        $this->assertSame($bundle->id, $item->bundle_id);
        $this->assertSame('1-Person Kit', $item->bundle_name);
        $this->assertSame('test-1-person-kit', $item->sku);
        $this->assertSame('59.98', $item->line_total);

        $resource = (new OrderItemResource($item))->resolve();
        $this->assertSame($shell->id, $resource['product_variant_id']);
        $this->assertSame('test-1-person-kit', $resource['product_slug']);

        // Placement reserves only the real components - never the shell.
        $this->assertSame([20, 2], $this->stock($pack));
        $this->assertSame([20, 4], $this->stock($case));
        $this->assertSame([KitShellProductService::NOMINAL_STOCK, 0], $this->stock($shell));

        app(OrderService::class)->confirmPayment($order);

        // Exactly one decrement per component (no double processing via
        // the new product_variant_id), and the shell untouched.
        $this->assertSame([18, 0], $this->stock($pack));
        $this->assertSame([16, 0], $this->stock($case));
        $this->assertSame([16, 0], $this->stock($scraper));
        $this->assertSame([KitShellProductService::NOMINAL_STOCK, 0], $this->stock($shell));
    }

    #[Test]
    public function cancelling_a_pending_kit_order_with_a_shell_releases_only_real_components(): void
    {
        [$bundle, $pack] = $this->kit();
        $shell = app(KitShellProductService::class)->sync($bundle);

        $order = $this->placeOrder([['/api/v1/cart/bundles', ['bundle_id' => $bundle->id, 'quantity' => 2]]]);
        app(OrderService::class)->cancel($order);

        $this->assertSame([20, 0], $this->stock($pack));
        $this->assertSame([KitShellProductService::NOMINAL_STOCK, 0], $this->stock($shell));
    }
}
