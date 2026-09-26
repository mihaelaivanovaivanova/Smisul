<?php

namespace Tests\Feature\Admin;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Price;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManualOrderAdminTest extends TestCase
{
    use RefreshDatabase;

    private function payload(ProductVariant $variant, array $overrides = []): array
    {
        return array_merge([
            'customer_first_name' => 'Ivan',
            'customer_last_name' => 'Ivanov',
            'customer_phone' => '+359888123456',
            'shipping_carrier' => 'speedy',
            'shipping_delivery_type' => 'office',
            'shipping_office_id' => '780',
            'shipping_office_name' => 'Sofia Office',
            'shipping_office_city' => 'Sofia',
            'shipping_office_address' => 'ul. Test 1',
            'shipping_price' => 5.99,
            'payment_method' => 'cash_on_delivery',
            'cod_fee' => 0.5,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ], $overrides);
    }

    #[Test]
    public function a_customer_cannot_create_a_manual_order(): void
    {
        $customer = User::factory()->create();
        $variant = ProductVariant::factory()->create();

        $this->actingAs($customer)->postJson('/api/v1/admin/orders', $this->payload($variant))
            ->assertForbidden();
    }

    #[Test]
    public function an_administrator_can_create_a_manual_order_with_no_email(): void
    {
        $admin = User::factory()->administrator()->create();
        $variant = ProductVariant::factory()->create();
        Price::factory()->for($variant, 'productVariant')->create(['amount' => 10]);
        Inventory::factory()->for($variant, 'productVariant')->create(['quantity_on_hand' => 20, 'quantity_reserved' => 0]);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/orders', $this->payload($variant));

        $response->assertCreated();
        $response->assertJsonPath('data.customer.email', null);
        $response->assertJsonPath('data.customer.first_name', 'Ivan');
        $response->assertJsonPath('data.status', 'confirmed');
        $response->assertJsonPath('data.items.0.quantity', 2);
        $response->assertJsonPath('data.totals.subtotal', 20);
        $response->assertJsonPath('data.totals.shipping_total', 5.99);
        $response->assertJsonPath('data.totals.cod_fee', 0.5);
        $response->assertJsonPath('data.totals.grand_total', 26.49);
        $response->assertJsonPath('data.shipping.office_id', '780');

        $this->assertDatabaseHas('orders', ['id' => $response->json('data.id'), 'customer_email' => null]);
    }

    #[Test]
    public function creating_a_manual_order_decrements_stock_immediately(): void
    {
        $admin = User::factory()->administrator()->create();
        $variant = ProductVariant::factory()->create();
        Price::factory()->for($variant, 'productVariant')->create(['amount' => 10]);
        Inventory::factory()->for($variant, 'productVariant')->create(['quantity_on_hand' => 20, 'quantity_reserved' => 0]);

        $this->actingAs($admin)->postJson('/api/v1/admin/orders', $this->payload($variant, ['quantity' => 3]))
            ->assertCreated();

        $this->assertSame(17, $variant->inventory->fresh()->quantity_on_hand);
    }

    #[Test]
    public function creating_a_manual_order_with_insufficient_stock_is_rejected(): void
    {
        $admin = User::factory()->administrator()->create();
        $variant = ProductVariant::factory()->create();
        Price::factory()->for($variant, 'productVariant')->create(['amount' => 10]);
        Inventory::factory()->for($variant, 'productVariant')->create(['quantity_on_hand' => 1, 'quantity_reserved' => 0]);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/orders', $this->payload($variant, ['quantity' => 5]));

        $response->assertStatus(422);
        $this->assertSame(0, Order::count());
        $this->assertSame(1, $variant->inventory->fresh()->quantity_on_hand);
    }

    #[Test]
    public function the_cod_fee_can_be_zeroed_out_even_when_cash_on_delivery_is_selected(): void
    {
        $admin = User::factory()->administrator()->create();
        $variant = ProductVariant::factory()->create();
        Price::factory()->for($variant, 'productVariant')->create(['amount' => 10]);
        Inventory::factory()->for($variant, 'productVariant')->create(['quantity_on_hand' => 20, 'quantity_reserved' => 0]);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/orders', $this->payload($variant, ['cod_fee' => 0]));

        $response->assertCreated();
        $response->assertJsonPath('data.totals.cod_fee', 0);
    }

    #[Test]
    public function street_address_delivery_is_rejected_for_a_manual_order(): void
    {
        $admin = User::factory()->administrator()->create();
        $variant = ProductVariant::factory()->create();
        Price::factory()->for($variant, 'productVariant')->create(['amount' => 10]);
        Inventory::factory()->for($variant, 'productVariant')->create(['quantity_on_hand' => 20, 'quantity_reserved' => 0]);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/orders', $this->payload($variant, [
            'shipping_delivery_type' => 'address',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('shipping_delivery_type');
    }

    #[Test]
    public function cash_on_delivery_is_rejected_for_a_box_now_manual_order(): void
    {
        $admin = User::factory()->administrator()->create();
        $variant = ProductVariant::factory()->create();
        Price::factory()->for($variant, 'productVariant')->create(['amount' => 10]);
        Inventory::factory()->for($variant, 'productVariant')->create(['quantity_on_hand' => 20, 'quantity_reserved' => 0]);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/orders', $this->payload($variant, [
            'shipping_carrier' => 'box_now',
            'shipping_delivery_type' => 'locker',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('payment_method');
    }

    #[Test]
    public function a_manual_order_counts_toward_admin_statistics_despite_having_no_email(): void
    {
        $admin = User::factory()->administrator()->create();
        $variant = ProductVariant::factory()->create();
        Price::factory()->for($variant, 'productVariant')->create(['amount' => 10]);
        Inventory::factory()->for($variant, 'productVariant')->create(['quantity_on_hand' => 20, 'quantity_reserved' => 0]);

        $this->actingAs($admin)->postJson('/api/v1/admin/orders', $this->payload($variant))->assertCreated();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/orders/statistics');

        $response->assertOk();
        $response->assertJsonPath('data.total_orders', 1);
        $response->assertJsonPath('data.orders_by_status.confirmed', 1);
    }
}
