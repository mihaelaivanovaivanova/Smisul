<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\FunnelConfig;
use App\Models\Product;

/**
 * Backs the "funnel mode" toggle: a single admin-controlled flag that
 * swaps the storefront's homepage for a single-product landing page (see
 * FunnelContentService for that page's editable copy). Resolution here
 * mirrors ContentBlockService::resolveFeaturedProductSlug()'s
 * graceful-null pattern — an unpublished/deleted product or a stale
 * variant reference silently drops out rather than breaking the public
 * payload.
 */
class FunnelService
{
    public function __construct(private readonly FunnelContentService $content) {}

    /**
     * @return array{enabled: bool, product_slug: ?string, packages: list<array<string, mixed>>, content: array<string, array<string, mixed>>}
     */
    public function publicPayload(): array
    {
        $config = FunnelConfig::current();
        $product = $this->resolveProduct($config->product_id);

        return [
            'enabled' => $config->is_enabled,
            'product_slug' => $product?->slug,
            'packages' => $this->resolvePackages($product, $config->packages ?? []),
            'content' => $this->content->all(),
        ];
    }

    /**
     * The admin's own view of the config — unlike publicPayload(), this
     * returns the raw product_id/packages as stored (even if the product
     * was since unpublished), so the admin can see and fix a stale
     * reference instead of it silently vanishing.
     *
     * @return array{is_enabled: bool, product_id: ?int, packages: list<array<string, mixed>>, content: array<string, array<string, mixed>>}
     */
    public function adminPayload(): array
    {
        $config = FunnelConfig::current();

        return [
            'is_enabled' => $config->is_enabled,
            'product_id' => $config->product_id,
            'packages' => $config->packages ?? [],
            'content' => $this->content->all(),
        ];
    }

    public function toggle(bool $isEnabled): FunnelConfig
    {
        $config = FunnelConfig::current();
        $config->update(['is_enabled' => $isEnabled]);

        return $config;
    }

    /**
     * @param  list<array<string, mixed>>  $packages
     */
    public function updatePackages(int $productId, array $packages): FunnelConfig
    {
        $config = FunnelConfig::current();
        $config->update(['product_id' => $productId, 'packages' => $packages]);

        return $config;
    }

    private function resolveProduct(?int $productId): ?Product
    {
        if ($productId === null) {
            return null;
        }

        return Product::query()
            ->where('id', $productId)
            ->where('status', ProductStatus::Published)
            ->with('variants')
            ->first();
    }

    /**
     * @param  list<array<string, mixed>>  $packages
     * @return list<array<string, mixed>>
     */
    private function resolvePackages(?Product $product, array $packages): array
    {
        if ($product === null) {
            return [];
        }

        $variantIds = $product->variants->pluck('id');

        return collect($packages)
            ->filter(fn (array $package) => $variantIds->contains($package['variant_id'] ?? null))
            ->values()
            ->all();
    }
}
