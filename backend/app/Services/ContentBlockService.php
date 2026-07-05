<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\ContentBlock;
use App\Models\Product;
use InvalidArgumentException;

/**
 * Backs both the public homepage's dynamic sections and the admin Content
 * editor. Each homepage section is one row (key = "homepage.{section}"),
 * so admins can update a section independently without touching the rest.
 */
class ContentBlockService
{
    public const HOMEPAGE_SECTIONS = ['hero', 'featured', 'benefits', 'usage', 'trust', 'delivery', 'bio', 'faq'];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function homepage(): array
    {
        $blocks = ContentBlock::query()
            ->where('key', 'like', 'homepage.%')
            ->get()
            ->mapWithKeys(fn (ContentBlock $block) => [substr($block->key, strlen('homepage.')) => $block->content]);

        $sections = collect(self::HOMEPAGE_SECTIONS)
            ->mapWithKeys(fn (string $section) => [$section => $blocks[$section] ?? []])
            ->all();

        $sections['featured']['product_id'] ??= null;
        $sections['featured']['product_slug'] = $this->resolveFeaturedProductSlug($sections['featured']['product_id']);

        return $sections;
    }

    /**
     * The admin picks a product by id (a stable reference); the storefront
     * fetches by slug (the public products API has no by-id lookup). This
     * resolves the two, and silently falls back to null — rather than
     * exposing a broken reference — if the chosen product was since
     * unpublished or deleted, so HomePage's "use the newest product
     * instead" fallback kicks in automatically.
     */
    private function resolveFeaturedProductSlug(?int $productId): ?string
    {
        if ($productId === null) {
            return null;
        }

        return Product::query()
            ->where('id', $productId)
            ->where('status', ProductStatus::Published)
            ->value('slug');
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function updateHomepageSection(string $section, array $content): array
    {
        if (! in_array($section, self::HOMEPAGE_SECTIONS, true)) {
            throw new InvalidArgumentException("Unknown homepage section [{$section}].");
        }

        $block = ContentBlock::query()->updateOrCreate(['key' => "homepage.{$section}"], ['content' => $content]);

        return $block->content;
    }
}
