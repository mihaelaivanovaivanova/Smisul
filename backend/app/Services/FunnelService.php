<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\FunnelConfig;
use App\Models\FunnelVariant;
use App\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Backs the "funnel mode" toggle: a single admin-controlled flag that
 * swaps the storefront's homepage for a single-product landing page (see
 * FunnelContentService for that page's editable copy). Resolution here
 * mirrors ContentBlockService::resolveFeaturedProductSlug()'s
 * graceful-null pattern — an unpublished/deleted product or a stale
 * variant reference silently drops out rather than breaking the public
 * payload.
 *
 * A funnel "variant" is a different ad angle (whitening, fresh breath, ...)
 * served at /{product-slug}/{variant-slug} instead of "/" — see
 * FunnelVariant. It inherits the base config's product/packages unless it
 * carries its own, and always inherits content sections it hasn't
 * overridden (see FunnelContentService).
 */
class FunnelService
{
    public function __construct(private readonly FunnelContentService $content) {}

    /**
     * @return array{enabled: bool, product_slug: ?string, packages: list<array<string, mixed>>, content: array<string, array<string, mixed>>, variant_slug: ?string, meta_title: ?string, meta_description: ?string}
     */
    public function publicPayload(?string $variantSlug = null): array
    {
        $config = FunnelConfig::current();
        $variant = $variantSlug !== null ? $this->findActiveVariant($variantSlug) : null;
        $productId = $config->product_id;
        $packages = $config->packages ?? [];

        if ($variant !== null) {
            $productId = $variant->product_id ?? $productId;
            $packages = $variant->packages ?? $packages;
        }

        $product = $this->resolveProduct($productId);

        return [
            'enabled' => $config->is_enabled,
            'product_slug' => $product?->slug,
            'packages' => $this->resolvePackages($product, $packages),
            'content' => $this->content->all($variantSlug),
            'variant_slug' => $variant?->slug,
            'meta_title' => $variant?->meta_title,
            'meta_description' => $variant?->meta_description,
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

    /**
     * @return array{id: int, slug: string, name: string, is_active: bool, product_id: ?int, packages: list<array<string, mixed>>, meta_title: ?string, meta_description: ?string, content: array<string, array<string, mixed>>, overridden_sections: list<string>}
     */
    public function adminVariantPayload(FunnelVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'slug' => $variant->slug,
            'name' => $variant->name,
            'is_active' => $variant->is_active,
            'product_id' => $variant->product_id,
            'packages' => $variant->packages ?? [],
            'meta_title' => $variant->meta_title,
            'meta_description' => $variant->meta_description,
            'content' => $this->content->all($variant->slug),
            'overridden_sections' => $this->content->overriddenSections($variant->slug),
        ];
    }

    /**
     * @return list<array{id: int, slug: string, name: string, is_active: bool}>
     */
    public function listVariants(): array
    {
        return FunnelVariant::query()
            ->orderBy('name')
            ->get()
            ->map(fn (FunnelVariant $variant) => [
                'id' => $variant->id,
                'slug' => $variant->slug,
                'name' => $variant->name,
                'is_active' => $variant->is_active,
            ])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>|null  $packages
     */
    public function createVariant(string $slug, string $name, ?int $productId, ?array $packages, bool $isActive): FunnelVariant
    {
        return FunnelVariant::query()->create([
            'slug' => $slug,
            'name' => $name,
            'product_id' => $productId,
            'packages' => $packages,
            'is_active' => $isActive,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateVariant(FunnelVariant $variant, array $attributes): FunnelVariant
    {
        $variant->update($attributes);

        return $variant;
    }

    public function deleteVariant(FunnelVariant $variant): void
    {
        $this->content->deleteVariantContent($variant->slug);
        $variant->delete();
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

    private function findActiveVariant(string $slug): FunnelVariant
    {
        $variant = FunnelVariant::query()->where('slug', $slug)->where('is_active', true)->first();

        if ($variant === null) {
            throw new ModelNotFoundException("No active funnel variant [{$slug}].");
        }

        return $variant;
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
