<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PromotionAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_guest_cannot_list_promotions(): void
    {
        $this->getJson('/api/v1/admin/promotions')->assertUnauthorized();
    }

    #[Test]
    public function a_customer_cannot_list_promotions(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->getJson('/api/v1/admin/promotions')->assertForbidden();
    }

    #[Test]
    public function an_administrator_can_create_a_percentage_promotion_scoped_to_products(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/promotions', [
            'name' => 'Summer Sale',
            'type' => 'percentage',
            'value' => 20,
            'product_ids' => [$product->id],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.value', 20);
        $this->assertDatabaseHas('promotion_product', ['product_id' => $product->id]);
    }

    #[Test]
    public function a_percentage_promotion_cannot_exceed_100(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/promotions', [
            'name' => 'Too Much',
            'type' => 'percentage',
            'value' => 150,
        ])->assertUnprocessable()->assertJsonValidationErrors('value');
    }

    #[Test]
    public function a_fixed_amount_promotion_may_exceed_100(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/promotions', [
            'name' => 'Big Discount',
            'type' => 'fixed_amount',
            'value' => 150,
        ])->assertCreated();
    }

    #[Test]
    public function promotion_codes_must_be_unique(): void
    {
        $admin = User::factory()->administrator()->create();
        Promotion::factory()->withCode()->create(['code' => 'SAVE10']);

        $this->actingAs($admin)->postJson('/api/v1/admin/promotions', [
            'name' => 'Another Sale',
            'type' => 'percentage',
            'value' => 10,
            'code' => 'SAVE10',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    #[Test]
    public function an_administrator_can_delete_a_promotion(): void
    {
        $admin = User::factory()->administrator()->create();
        $promotion = Promotion::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/v1/admin/promotions/{$promotion->id}")->assertNoContent();

        $this->assertSoftDeleted($promotion);
    }
}
