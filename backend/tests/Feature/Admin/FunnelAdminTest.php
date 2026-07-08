<?php

namespace Tests\Feature\Admin;

use App\Enums\Currency;
use App\Enums\VariantStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FunnelAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_customer_cannot_view_or_update_funnel_config(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->getJson('/api/v1/admin/funnel')->assertForbidden();
        $this->actingAs($customer)->putJson('/api/v1/admin/funnel/toggle', ['is_enabled' => true])->assertForbidden();
    }

    #[Test]
    public function a_guest_is_unauthenticated_on_funnel_admin_endpoints(): void
    {
        $this->getJson('/api/v1/admin/funnel')->assertUnauthorized();
    }

    #[Test]
    public function an_administrator_can_view_the_funnel_config(): void
    {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/funnel');

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['is_enabled', 'product_id', 'packages', 'content']]);
        $response->assertJsonPath('data.is_enabled', false);
    }

    #[Test]
    public function an_administrator_can_toggle_funnel_mode(): void
    {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->putJson('/api/v1/admin/funnel/toggle', ['is_enabled' => true]);

        $response->assertOk();
        $response->assertJsonPath('data.is_enabled', true);

        $public = $this->getJson('/api/v1/funnel');
        $public->assertJsonPath('data.enabled', true);
    }

    #[Test]
    public function toggling_requires_a_boolean(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->putJson('/api/v1/admin/funnel/toggle', ['is_enabled' => 'not-a-boolean'])
            ->assertUnprocessable();
    }

    #[Test]
    public function an_administrator_can_set_the_product_and_three_packages(): void
    {
        $admin = User::factory()->administrator()->create();
        [$product, $variants] = $this->productWithVariants(3);

        $response = $this->actingAs($admin)->putJson('/api/v1/admin/funnel/packages', [
            'product_id' => $product->id,
            'packages' => [
                ['variant_id' => $variants[0]->id, 'badge' => 'B1', 'detail' => 'D1', 'value_label' => 'V1', 'button_text' => 'Buy 1'],
                ['variant_id' => $variants[1]->id, 'badge' => 'B2', 'detail' => 'D2', 'value_label' => 'V2', 'button_text' => 'Buy 2'],
                ['variant_id' => $variants[2]->id, 'badge' => 'B3', 'detail' => 'D3', 'value_label' => 'V3', 'button_text' => 'Buy 3'],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonCount(3, 'data.packages');

        $public = $this->getJson('/api/v1/funnel');
        $public->assertJsonPath('data.product_slug', $product->slug);
        $public->assertJsonCount(3, 'data.packages');
    }

    #[Test]
    public function packages_must_be_exactly_three(): void
    {
        $admin = User::factory()->administrator()->create();
        [$product, $variants] = $this->productWithVariants(2);

        $this->actingAs($admin)->putJson('/api/v1/admin/funnel/packages', [
            'product_id' => $product->id,
            'packages' => [
                ['variant_id' => $variants[0]->id, 'badge' => 'B1', 'detail' => 'D1', 'value_label' => 'V1', 'button_text' => 'Buy 1'],
                ['variant_id' => $variants[1]->id, 'badge' => 'B2', 'detail' => 'D2', 'value_label' => 'V2', 'button_text' => 'Buy 2'],
            ],
        ])->assertUnprocessable();
    }

    #[Test]
    public function a_package_variant_not_belonging_to_the_chosen_product_is_rejected(): void
    {
        $admin = User::factory()->administrator()->create();
        [$product, $variants] = $this->productWithVariants(3);
        [, $otherVariants] = $this->productWithVariants(1);

        $this->actingAs($admin)->putJson('/api/v1/admin/funnel/packages', [
            'product_id' => $product->id,
            'packages' => [
                ['variant_id' => $otherVariants[0]->id, 'badge' => 'B1', 'detail' => 'D1', 'value_label' => 'V1', 'button_text' => 'Buy 1'],
                ['variant_id' => $variants[1]->id, 'badge' => 'B2', 'detail' => 'D2', 'value_label' => 'V2', 'button_text' => 'Buy 2'],
                ['variant_id' => $variants[2]->id, 'badge' => 'B3', 'detail' => 'D3', 'value_label' => 'V3', 'button_text' => 'Buy 3'],
            ],
        ])->assertUnprocessable();
    }

    #[Test]
    public function an_administrator_can_update_the_hero_content_section(): void
    {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->putJson('/api/v1/admin/funnel/content/hero', [
            'badge' => 'New badge',
            'title' => 'New title',
            'body' => 'New body',
            'highlight' => 'New highlight',
            'cta_primary' => 'Buy now',
            'cta_secondary' => 'Learn more',
            'bullets' => ['One', 'Two'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'New title');
        $this->assertDatabaseHas('content_blocks', ['key' => 'funnel.hero']);

        $public = $this->getJson('/api/v1/funnel');
        $public->assertJsonPath('data.content.hero.title', 'New title');
    }

    #[Test]
    public function updating_the_hero_section_requires_all_fields(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->putJson('/api/v1/admin/funnel/content/hero', ['badge' => 'Only badge'])
            ->assertUnprocessable();
    }

    #[Test]
    public function an_administrator_can_update_the_faq_repeatable_list(): void
    {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->putJson('/api/v1/admin/funnel/content/faq', [
            'title' => 'FAQ',
            'items' => [
                ['question' => 'Q1', 'answer' => 'A1'],
                ['question' => 'Q2', 'answer' => 'A2'],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonCount(2, 'data.items');
    }

    #[Test]
    public function an_unknown_funnel_content_section_is_not_found(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->putJson('/api/v1/admin/funnel/content/not-a-section', ['foo' => 'bar'])
            ->assertNotFound();
    }

    /**
     * @return array{0: Product, 1: list<ProductVariant>}
     */
    private function productWithVariants(int $count): array
    {
        $product = Product::factory()->published()->create();

        $variants = collect(range(1, $count))->map(function () use ($product) {
            $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);
            $variant->inventory()->create(['quantity_on_hand' => 10]);
            $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 9.99]);

            return $variant;
        })->all();

        return [$product, $variants];
    }
}
