<?php

namespace Tests\Feature;

use App\Enums\LegalDocumentType;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\FunnelConfig;
use App\Models\LegalDocument;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_serves_an_xml_sitemap_including_categories_products_and_legal_pages(): void
    {
        $category = Category::factory()->create(['slug' => 'gifts', 'is_active' => true]);
        Category::factory()->create(['slug' => 'hidden', 'is_active' => false]);

        $product = Product::factory()->published()->create(['slug' => 'candle']);
        Product::factory()->create(['slug' => 'draft-product', 'status' => ProductStatus::Draft]);

        LegalDocument::factory()->create(['type' => LegalDocumentType::TermsOfService, 'is_current' => true]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');

        $xml = $response->getContent();
        $this->assertStringContainsString("/categories/{$category->slug}", $xml);
        $this->assertStringNotContainsString('/categories/hidden', $xml);
        $this->assertStringContainsString("/products/{$product->slug}", $xml);
        $this->assertStringNotContainsString('/products/draft-product', $xml);
        $this->assertStringContainsString('/legal/terms-of-service', $xml);
    }

    #[Test]
    public function it_omits_legal_document_types_with_no_published_version(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringNotContainsString('/legal/', $response->getContent());
    }

    #[Test]
    public function it_omits_search_when_funnel_mode_is_enabled(): void
    {
        $before = $this->get('/sitemap.xml');
        $this->assertStringContainsString('/search', $before->getContent());

        FunnelConfig::current()->update(['is_enabled' => true]);

        $after = $this->get('/sitemap.xml');
        $this->assertStringNotContainsString('/search', $after->getContent());
    }

    #[Test]
    public function the_backend_robots_txt_disallows_everything(): void
    {
        // Static assets under public/ aren't dispatched through the
        // framework kernel in feature tests (a real webserver would serve
        // this file directly) — read it straight off disk instead.
        $contents = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Disallow: /', $contents);
    }
}
