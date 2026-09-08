<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\FunnelConfig;
use App\Models\LegalDocument;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Builds the sitemap.xml entry list. URLs point at the FRONTEND's domain
 * (config('app.frontend_url')), not this API's own — the SPA is what
 * search engines actually need to crawl, this backend is just where the
 * data (and, in production, the sitemap route itself via a reverse-proxy
 * rewrite — see docs/legal-gdpr-seo.md) comes from.
 */
class SitemapService
{
    /**
     * @return list<array{loc: string, changefreq: string, priority: string, lastmod?: string}>
     */
    public function entries(): array
    {
        $baseUrl = rtrim((string) config('app.frontend_url'), '/');

        $entries = [
            ['loc' => $baseUrl.'/', 'changefreq' => 'daily', 'priority' => '1.0'],
        ];

        // /search redirects away to / when funnel mode is on (see
        // FunnelSearchGuard on the frontend) — listing it would put a
        // redirecting URL in the sitemap, which search engines penalize.
        if (! FunnelConfig::current()->is_enabled) {
            $entries[] = ['loc' => $baseUrl.'/search', 'changefreq' => 'daily', 'priority' => '0.6'];
        }

        // lastmod is only added where there's a real "last changed"
        // timestamp behind it (updated_at / published_at) - the static
        // entries above (/, /search) and /about below have no such signal,
        // and stamping them with now() on every sitemap request would be a
        // fake freshness signal, not a real one.
        foreach ($this->categories() as $category) {
            $entries[] = [
                'loc' => "{$baseUrl}/categories/{$category->slug}",
                'changefreq' => 'weekly',
                'priority' => '0.8',
                'lastmod' => $category->updated_at->toAtomString(),
            ];
        }

        foreach ($this->products() as $product) {
            $entries[] = [
                'loc' => "{$baseUrl}/products/{$product->slug}",
                'changefreq' => 'weekly',
                'priority' => '0.9',
                'lastmod' => $product->updated_at->toAtomString(),
            ];
        }

        foreach ($this->legalDocuments() as $document) {
            $entries[] = [
                'loc' => "{$baseUrl}/legal/{$document->type->slug()}",
                'changefreq' => 'monthly',
                'priority' => '0.3',
                'lastmod' => $document->published_at->toAtomString(),
            ];
        }

        $entries[] = ['loc' => $baseUrl.'/about', 'changefreq' => 'monthly', 'priority' => '0.4'];

        return $entries;
    }

    /**
     * @return Collection<int, Category>
     */
    private function categories(): Collection
    {
        return Category::query()->where('is_active', true)->get(['slug', 'updated_at']);
    }

    /**
     * @return Collection<int, Product>
     */
    private function products(): Collection
    {
        return Product::query()->where('status', ProductStatus::Published)->get(['slug', 'updated_at']);
    }

    /**
     * Only types with an actually-published current document — listing a
     * type with nothing to show would put a 404 in the sitemap, which
     * search engines specifically dislike.
     *
     * @return Collection<int, LegalDocument>
     */
    private function legalDocuments(): Collection
    {
        return LegalDocument::query()->where('is_current', true)->get(['type', 'published_at']);
    }
}
