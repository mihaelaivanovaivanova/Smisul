<?php

namespace Tests\Feature\Favorites;

use App\Models\Favorite;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_guest_cannot_access_favorites(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->getJson('/api/v1/customer/favorites')->assertUnauthorized();
        $this->postJson('/api/v1/customer/favorites', ['product_variant_id' => $variant->id])->assertUnauthorized();
        $this->deleteJson('/api/v1/customer/favorites/1')->assertUnauthorized();
    }

    #[Test]
    public function an_administrator_cannot_use_favorites(): void
    {
        $admin = User::factory()->administrator()->create();
        $variant = ProductVariant::factory()->create();

        $this->actingAs($admin)->getJson('/api/v1/customer/favorites')->assertForbidden();
        $this->actingAs($admin)->postJson('/api/v1/customer/favorites', ['product_variant_id' => $variant->id])
            ->assertForbidden();
    }

    #[Test]
    public function a_customer_can_list_their_favorites(): void
    {
        $customer = User::factory()->create();
        Favorite::factory()->for($customer)->create();
        Favorite::factory()->create(); // someone else's favorite

        $response = $this->actingAs($customer)->getJson('/api/v1/customer/favorites');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function a_customer_can_add_a_favorite(): void
    {
        $customer = User::factory()->create();
        $variant = ProductVariant::factory()->create();

        $response = $this->actingAs($customer)->postJson('/api/v1/customer/favorites', [
            'product_variant_id' => $variant->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.product_variant.id', $variant->id);
        $this->assertDatabaseHas('favorites', ['user_id' => $customer->id, 'product_variant_id' => $variant->id]);
    }

    #[Test]
    public function adding_a_favorite_requires_a_valid_product_variant(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->postJson('/api/v1/customer/favorites', ['product_variant_id' => 999999])
            ->assertUnprocessable();
    }

    #[Test]
    public function a_customer_cannot_favorite_the_same_variant_twice(): void
    {
        $customer = User::factory()->create();
        $variant = ProductVariant::factory()->create();
        Favorite::factory()->for($customer)->for($variant, 'productVariant')->create();

        $this->actingAs($customer)->postJson('/api/v1/customer/favorites', ['product_variant_id' => $variant->id])
            ->assertUnprocessable();

        $this->assertSame(1, Favorite::query()->where('user_id', $customer->id)->count());
    }

    #[Test]
    public function a_customer_can_remove_their_own_favorite(): void
    {
        $customer = User::factory()->create();
        $favorite = Favorite::factory()->for($customer)->create();

        $this->actingAs($customer)->deleteJson("/api/v1/customer/favorites/{$favorite->id}")
            ->assertNoContent();

        $this->assertModelMissing($favorite);
    }

    #[Test]
    public function a_customer_cannot_remove_another_customers_favorite(): void
    {
        $customer = User::factory()->create();
        $favorite = Favorite::factory()->create(); // belongs to someone else

        $this->actingAs($customer)->deleteJson("/api/v1/customer/favorites/{$favorite->id}")
            ->assertForbidden();

        $this->assertModelExists($favorite);
    }

    #[Test]
    public function a_customer_can_check_whether_a_variant_is_favorited(): void
    {
        $customer = User::factory()->create();
        $variant = ProductVariant::factory()->create();

        $this->actingAs($customer)->getJson("/api/v1/customer/favorites/check/{$variant->id}")
            ->assertOk()
            ->assertJsonPath('data.is_favorited', false);

        Favorite::factory()->for($customer)->for($variant, 'productVariant')->create();

        $this->actingAs($customer)->getJson("/api/v1/customer/favorites/check/{$variant->id}")
            ->assertOk()
            ->assertJsonPath('data.is_favorited', true);
    }

    #[Test]
    public function a_customer_can_see_their_favorite_count(): void
    {
        $customer = User::factory()->create();
        Favorite::factory()->for($customer)->count(3)->create();

        $this->actingAs($customer)->getJson('/api/v1/customer/favorites/count')
            ->assertOk()
            ->assertJsonPath('data.count', 3);
    }
}
