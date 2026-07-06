<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\LegalDocument;
use App\Models\Product;

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
     * @return list<array{loc: string, changefreq: string, priority: string}>
     */
    public function entries(): array
    {
        $baseUrl = rtrim((string) config('app.frontend_url'), '/');

        $entries = [
            ['loc' => $baseUrl.'/', 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => $baseUrl.'/search', 'changefreq' => 'daily', 'priority' => '0.6'],
        ];

        foreach ($this->categorySlugs() as $slug) {
            $entries[] = ['loc' => "{$baseUrl}/categories/{$slug}", 'changefreq' => 'weekly', 'priority' => '0.8'];
        }

        foreach ($this->productSlugs() as $slug) {
            $entries[] = ['loc' => "{$baseUrl}/products/{$slug}", 'changefreq' => 'weekly', 'priority' => '0.9'];
        }

        foreach ($this->legalDocumentSlugs() as $slug) {
            $entries[] = ['loc' => "{$baseUrl}/legal/{$slug}", 'changefreq' => 'monthly', 'priority' => '0.3'];
        }

        $entries[] = ['loc' => $baseUrl.'/about', 'changefreq' => 'monthly', 'priority' => '0.4'];
        $entries[] = ['loc' => $baseUrl.'/contact', 'changefreq' => 'monthly', 'priority' => '0.4'];

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function categorySlugs(): array
    {
        return Category::query()->where('is_active', true)->pluck('slug')->all();
    }

    /**
     * @return list<string>
     */
    private function productSlugs(): array
    {
        return Product::query()->where('status', ProductStatus::Published)->pluck('slug')->all();
    }

    /**
     * Only types with an actually-published current document — listing a
     * type with nothing to show would put a 404 in the sitemap, which
     * search engines specifically dislike.
     *
     * @return list<string>
     */
    private function legalDocumentSlugs(): array
    {
        return LegalDocument::query()
            ->where('is_current', true)
            ->get()
            ->map(fn (LegalDocument $document) => $document->type->slug())
            ->all();
    }
}
