<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\VariantStatus;
use App\Models\ContentBlock;
use App\Models\FunnelConfig;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FunnelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_funnel_payload_is_publicly_readable_and_disabled_by_default(): void
    {
        $response = $this->getJson('/api/v1/funnel');

        $response->assertOk();
        $response->assertJsonPath('data.enabled', false);
        $response->assertJsonPath('data.product_slug', null);
        $response->assertJsonPath('data.packages', []);
        $response->assertJsonStructure(['data' => ['content' => [
            'hero', 'dark_band', 'problem', 'benefits', 'ingredients', 'ritual',
            'how_to', 'packages_intro', 'labels', 'testimonials', 'faq', 'final_cta',
        ]]]);
    }

    #[Test]
    public function a_missing_content_section_defaults_to_an_empty_object(): void
    {
        $response = $this->getJson('/api/v1/funnel');

        $response->assertOk();
        $response->assertJsonPath('data.content.faq', []);
    }

    #[Test]
    public function an_enabled_config_resolves_the_product_slug_and_valid_packages(): void
    {
        $variant = $this->purchasableVariant();
        FunnelConfig::current()->update([
            'is_enabled' => true,
            'product_id' => $variant->product_id,
            'packages' => [
                ['variant_id' => $variant->id, 'badge' => 'B', 'detail' => 'D', 'value_label' => 'V', 'button_text' => 'Buy'],
            ],
        ]);

        $response = $this->getJson('/api/v1/funnel');

        $response->assertOk();
        $response->assertJsonPath('data.enabled', true);
        $response->assertJsonPath('data.product_slug', $variant->product->slug);
        $response->assertJsonCount(1, 'data.packages');
    }

    #[Test]
    public function a_package_referencing_a_variant_from_a_different_product_is_dropped(): void
    {
        $variant = $this->purchasableVariant();
        $otherVariant = $this->purchasableVariant();

        FunnelConfig::current()->update([
            'is_enabled' => true,
            'product_id' => $variant->product_id,
            'packages' => [
                ['variant_id' => $variant->id, 'badge' => 'B', 'detail' => 'D', 'value_label' => 'V', 'button_text' => 'Buy'],
                ['variant_id' => $otherVariant->id, 'badge' => 'Stale', 'detail' => 'D', 'value_label' => 'V', 'button_text' => 'Buy'],
            ],
        ]);

        $response = $this->getJson('/api/v1/funnel');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.packages');
        $response->assertJsonPath('data.packages.0.badge', 'B');
    }

    #[Test]
    public function an_unpublished_product_resolves_to_a_null_slug_and_no_packages(): void
    {
        $variant = $this->purchasableVariant(publish: false);

        FunnelConfig::current()->update([
            'is_enabled' => true,
            'product_id' => $variant->product_id,
            'packages' => [
                ['variant_id' => $variant->id, 'badge' => 'B', 'detail' => 'D', 'value_label' => 'V', 'button_text' => 'Buy'],
            ],
        ]);

        $response = $this->getJson('/api/v1/funnel');

        $response->assertOk();
        $response->assertJsonPath('data.product_slug', null);
        $response->assertJsonPath('data.packages', []);
    }

    #[Test]
    public function funnel_content_reflects_admin_edited_sections(): void
    {
        ContentBlock::query()->create(['key' => 'funnel.hero', 'content' => [
            'badge' => 'B', 'title' => 'T', 'body' => 'Body', 'highlight' => 'H',
            'cta_primary' => 'P', 'cta_secondary' => 'S', 'bullets' => ['One'],
        ]]);

        $response = $this->getJson('/api/v1/funnel');

        $response->assertOk();
        $response->assertJsonPath('data.content.hero.title', 'T');
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
