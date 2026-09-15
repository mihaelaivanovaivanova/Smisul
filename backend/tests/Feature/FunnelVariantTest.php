<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\VariantStatus;
use App\Models\ContentBlock;
use App\Models\FunnelConfig;
use App\Models\FunnelVariant;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FunnelVariantTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_unknown_variant_slug_is_not_found(): void
    {
        $this->getJson('/api/v1/funnel/does-not-exist')->assertNotFound();
    }

    #[Test]
    public function an_inactive_variant_is_not_found(): void
    {
        FunnelVariant::query()->create(['slug' => 'whitening', 'name' => 'Whitening', 'is_active' => false]);

        $this->getJson('/api/v1/funnel/whitening')->assertNotFound();
    }

    #[Test]
    public function an_active_variant_inherits_the_base_products_packages_and_unoverridden_content(): void
    {
        $baseVariant = $this->purchasableVariant();
        FunnelConfig::current()->update([
            'is_enabled' => true,
            'product_id' => $baseVariant->product_id,
            'packages' => [
                ['variant_id' => $baseVariant->id, 'badge' => 'B', 'detail' => 'D', 'value_label' => 'V', 'button_text' => 'Buy'],
            ],
        ]);
        ContentBlock::query()->create(['key' => 'funnel.faq', 'content' => ['title' => 'FAQ', 'items' => []]]);
        FunnelVariant::query()->create(['slug' => 'whitening', 'name' => 'Whitening', 'is_active' => true]);

        $response = $this->getJson('/api/v1/funnel/whitening');

        $response->assertOk();
        $response->assertJsonPath('data.variant_slug', 'whitening');
        $response->assertJsonPath('data.product_slug', $baseVariant->product->slug);
        $response->assertJsonCount(1, 'data.packages');
        $response->assertJsonPath('data.content.faq.title', 'FAQ');
    }

    #[Test]
    public function a_variant_section_override_takes_precedence_over_the_base_section(): void
    {
        ContentBlock::query()->create(['key' => 'funnel.hero', 'content' => ['title' => 'Base title']]);
        ContentBlock::query()->create(['key' => 'funnel.variant.whitening.hero', 'content' => ['title' => 'Whiter smile']]);
        FunnelVariant::query()->create(['slug' => 'whitening', 'name' => 'Whitening', 'is_active' => true]);

        $response = $this->getJson('/api/v1/funnel/whitening');

        $response->assertOk();
        $response->assertJsonPath('data.content.hero.title', 'Whiter smile');

        $base = $this->getJson('/api/v1/funnel');
        $base->assertJsonPath('data.content.hero.title', 'Base title');
    }

    #[Test]
    public function a_variant_with_its_own_product_and_packages_does_not_use_the_base_ones(): void
    {
        $baseVariant = $this->purchasableVariant();
        $ownVariant = $this->purchasableVariant();
        FunnelConfig::current()->update(['is_enabled' => true, 'product_id' => $baseVariant->product_id, 'packages' => []]);
        FunnelVariant::query()->create([
            'slug' => 'whitening',
            'name' => 'Whitening',
            'is_active' => true,
            'product_id' => $ownVariant->product_id,
            'packages' => [
                ['variant_id' => $ownVariant->id, 'badge' => 'B', 'detail' => 'D', 'value_label' => 'V', 'button_text' => 'Buy'],
            ],
        ]);

        $response = $this->getJson('/api/v1/funnel/whitening');

        $response->assertOk();
        $response->assertJsonPath('data.product_slug', $ownVariant->product->slug);
        $response->assertJsonCount(1, 'data.packages');
    }

    #[Test]
    public function a_customer_cannot_manage_funnel_variants(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->getJson('/api/v1/admin/funnel/variants')->assertForbidden();
        $this->actingAs($customer)->postJson('/api/v1/admin/funnel/variants', ['slug' => 'x', 'name' => 'X'])->assertForbidden();
    }

    #[Test]
    public function an_administrator_can_create_list_and_view_a_variant(): void
    {
        $admin = User::factory()->administrator()->create();

        $create = $this->actingAs($admin)->postJson('/api/v1/admin/funnel/variants', [
            'slug' => 'fresh-breath',
            'name' => 'Fresh Breath',
        ]);
        $create->assertCreated();
        $create->assertJsonPath('data.slug', 'fresh-breath');
        $create->assertJsonPath('data.is_active', true);

        $list = $this->actingAs($admin)->getJson('/api/v1/admin/funnel/variants');
        $list->assertOk();
        $list->assertJsonCount(1, 'data');

        $show = $this->actingAs($admin)->getJson('/api/v1/admin/funnel/variants/fresh-breath');
        $show->assertOk();
        $show->assertJsonPath('data.overridden_sections', []);
    }

    #[Test]
    public function creating_a_variant_requires_a_unique_slug(): void
    {
        $admin = User::factory()->administrator()->create();
        FunnelVariant::query()->create(['slug' => 'whitening', 'name' => 'Whitening']);

        $this->actingAs($admin)->postJson('/api/v1/admin/funnel/variants', ['slug' => 'whitening', 'name' => 'Again'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    #[Test]
    public function a_variant_cannot_set_packages_without_a_product(): void
    {
        $admin = User::factory()->administrator()->create();
        $variant = $this->purchasableVariant();

        $this->actingAs($admin)->postJson('/api/v1/admin/funnel/variants', [
            'slug' => 'whitening',
            'name' => 'Whitening',
            'packages' => [
                ['variant_id' => $variant->id, 'badge' => 'B', 'detail' => 'D', 'value_label' => 'V', 'button_text' => 'Buy'],
                ['variant_id' => $variant->id, 'badge' => 'B', 'detail' => 'D', 'value_label' => 'V', 'button_text' => 'Buy'],
                ['variant_id' => $variant->id, 'badge' => 'B', 'detail' => 'D', 'value_label' => 'V', 'button_text' => 'Buy'],
                ['variant_id' => $variant->id, 'badge' => 'B', 'detail' => 'D', 'value_label' => 'V', 'button_text' => 'Buy'],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('product_id');
    }

    #[Test]
    public function an_administrator_can_update_and_deactivate_a_variant(): void
    {
        $admin = User::factory()->administrator()->create();
        $variant = FunnelVariant::query()->create(['slug' => 'whitening', 'name' => 'Whitening', 'is_active' => true]);

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/funnel/variants/{$variant->slug}", [
            'name' => 'Whitening Power',
            'is_active' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Whitening Power');
        $response->assertJsonPath('data.is_active', false);

        $this->getJson('/api/v1/funnel/whitening')->assertNotFound();
    }

    #[Test]
    public function an_administrator_can_override_and_reset_a_variant_content_section(): void
    {
        $admin = User::factory()->administrator()->create();
        $variant = FunnelVariant::query()->create(['slug' => 'whitening', 'name' => 'Whitening', 'is_active' => true]);
        ContentBlock::query()->create(['key' => 'funnel.awareness', 'content' => ['title' => 'Base', 'subtitle' => 'S', 'body' => 'B']]);

        $update = $this->actingAs($admin)->putJson("/api/v1/admin/funnel/variants/{$variant->slug}/content/awareness", [
            'title' => 'Whitening claim, honestly', 'subtitle' => 'S', 'body' => 'B',
        ]);
        $update->assertOk();
        $update->assertJsonPath('data.title', 'Whitening claim, honestly');

        $show = $this->actingAs($admin)->getJson("/api/v1/admin/funnel/variants/{$variant->slug}");
        $show->assertJsonPath('data.overridden_sections', ['awareness']);

        $reset = $this->actingAs($admin)->deleteJson("/api/v1/admin/funnel/variants/{$variant->slug}/content/awareness");
        $reset->assertOk();
        $reset->assertJsonPath('data.overridden_sections', []);
        $reset->assertJsonPath('data.content.awareness.title', 'Base');
    }

    #[Test]
    public function deleting_a_variant_removes_its_content_overrides(): void
    {
        $admin = User::factory()->administrator()->create();
        $variant = FunnelVariant::query()->create(['slug' => 'whitening', 'name' => 'Whitening', 'is_active' => true]);
        ContentBlock::query()->create(['key' => 'funnel.variant.whitening.hero', 'content' => ['title' => 'X']]);

        $this->actingAs($admin)->deleteJson("/api/v1/admin/funnel/variants/{$variant->slug}")->assertNoContent();

        $this->assertDatabaseMissing('funnel_variants', ['slug' => 'whitening']);
        $this->assertDatabaseMissing('content_blocks', ['key' => 'funnel.variant.whitening.hero']);
    }

    private function purchasableVariant(bool $publish = true, int $stock = 10): ProductVariant
    {
        $product = $publish ? Product::factory()->published()->create() : Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Active]);
        $variant->inventory()->create(['quantity_on_hand' => $stock]);
        $variant->prices()->create(['currency' => Currency::EUR->value, 'amount' => 19.99]);

        return $variant;
    }
}
