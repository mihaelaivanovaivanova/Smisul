<?php

namespace Tests\Feature\Admin;

use App\Models\ContentBlock;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentBlockAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_customer_cannot_view_or_update_homepage_content(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->getJson('/api/v1/admin/content/homepage')->assertForbidden();
        $this->actingAs($customer)->putJson('/api/v1/admin/content/homepage/hero', ['eyebrow' => 'E', 'title' => 'T', 'subtitle' => 'S', 'cta' => 'C'])
            ->assertForbidden();
    }

    #[Test]
    public function a_guest_is_unauthenticated_on_content_admin_endpoints(): void
    {
        $this->getJson('/api/v1/admin/content/homepage')->assertUnauthorized();
    }

    #[Test]
    public function an_administrator_can_view_every_homepage_section(): void
    {
        $admin = User::factory()->administrator()->create();
        ContentBlock::query()->create(['key' => 'homepage.hero', 'content' => ['eyebrow' => 'E', 'title' => 'T', 'subtitle' => 'S', 'cta' => 'C']]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/content/homepage');

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['hero', 'featured', 'benefits', 'usage', 'trust', 'delivery', 'bio', 'faq']]);
        $response->assertJsonPath('data.hero.title', 'T');
    }

    #[Test]
    public function an_administrator_can_update_the_hero_section(): void
    {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->putJson('/api/v1/admin/content/homepage/hero', [
            'eyebrow' => 'New eyebrow',
            'title' => 'New title',
            'subtitle' => 'New subtitle',
            'cta' => 'New CTA',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'New title');
        $this->assertDatabaseHas('content_blocks', ['key' => 'homepage.hero']);

        $public = $this->getJson('/api/v1/content/homepage');
        $public->assertJsonPath('data.hero.title', 'New title');
    }

    #[Test]
    public function updating_hero_requires_all_fields(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->putJson('/api/v1/admin/content/homepage/hero', ['eyebrow' => 'Only eyebrow'])
            ->assertUnprocessable();
    }

    #[Test]
    public function an_administrator_can_update_a_list_section_with_repeatable_items(): void
    {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->putJson('/api/v1/admin/content/homepage/faq', [
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
    public function a_benefit_item_with_an_invalid_icon_is_rejected(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->putJson('/api/v1/admin/content/homepage/benefits', [
            'title' => 'Benefits',
            'lead' => 'Lead',
            'items' => [
                ['icon' => 'not-a-real-icon', 'title' => 'T', 'text' => 'Text'],
            ],
        ])->assertUnprocessable();
    }

    #[Test]
    public function a_featured_block_saved_before_product_selection_existed_still_reports_a_null_product_id(): void
    {
        $admin = User::factory()->administrator()->create();
        // Simulates a row saved before the product_id field was introduced.
        ContentBlock::query()->create(['key' => 'homepage.featured', 'content' => ['eyebrow' => 'E', 'title' => 'T', 'description' => 'D', 'cta' => 'C']]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/content/homepage');

        $response->assertOk();
        $response->assertJsonPath('data.featured.product_id', null);
        $response->assertJsonPath('data.featured.product_slug', null);
    }

    #[Test]
    public function an_administrator_can_feature_a_published_product(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = Product::factory()->published()->create();

        $response = $this->actingAs($admin)->putJson('/api/v1/admin/content/homepage/featured', [
            'eyebrow' => 'E', 'title' => 'T', 'description' => 'D', 'cta' => 'C',
            'product_id' => $product->id,
        ]);

        $response->assertOk();

        $public = $this->getJson('/api/v1/content/homepage');
        $public->assertJsonPath('data.featured.product_slug', $product->slug);
    }

    #[Test]
    public function featuring_a_draft_product_resolves_to_no_slug_on_the_public_endpoint(): void
    {
        $admin = User::factory()->administrator()->create();
        $draft = Product::factory()->create();

        $this->actingAs($admin)->putJson('/api/v1/admin/content/homepage/featured', [
            'eyebrow' => 'E', 'title' => 'T', 'description' => 'D', 'cta' => 'C',
            'product_id' => $draft->id,
        ])->assertOk();

        $public = $this->getJson('/api/v1/content/homepage');
        $public->assertJsonPath('data.featured.product_slug', null);
    }

    #[Test]
    public function featuring_a_nonexistent_product_is_rejected(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->putJson('/api/v1/admin/content/homepage/featured', [
            'eyebrow' => 'E', 'title' => 'T', 'description' => 'D', 'cta' => 'C',
            'product_id' => 999999,
        ])->assertUnprocessable();
    }

    #[Test]
    public function no_product_selected_resolves_to_a_null_slug(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->putJson('/api/v1/admin/content/homepage/featured', [
            'eyebrow' => 'E', 'title' => 'T', 'description' => 'D', 'cta' => 'C',
            'product_id' => null,
        ])->assertOk();

        $public = $this->getJson('/api/v1/content/homepage');
        $public->assertJsonPath('data.featured.product_slug', null);
    }

    #[Test]
    public function an_unknown_section_name_is_not_found(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->putJson('/api/v1/admin/content/homepage/not-a-section', ['foo' => 'bar'])
            ->assertNotFound();
    }
}
