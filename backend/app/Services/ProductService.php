<?php

namespace App\Services;

use App\DataTransferObjects\PriceData;
use App\DataTransferObjects\ProductData;
use App\DataTransferObjects\ProductFilterData;
use App\DataTransferObjects\ProductVariantData;
use App\Enums\Currency;
use App\Enums\ProductStatus;
use App\Exceptions\ProductNotFoundException;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService
{
    private const DEFAULT_EAGER_LOAD = [
        'categories.promotions',
        'variants.prices',
        'variants.inventory',
        'media',
        'promotions',
    ];

    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly ProductVariantService $variants,
        private readonly PriceService $prices,
        private readonly InventoryService $inventory,
    ) {}

    public function list(ProductFilterData $filters, bool $publishedOnly = true): LengthAwarePaginator
    {
        return $this->products->paginate($filters, $publishedOnly, self::DEFAULT_EAGER_LOAD);
    }

    public function findBySlug(string $slug, bool $publishedOnly = true): Product
    {
        $product = $this->products->findBySlug($slug, $publishedOnly);

        if ($product === null) {
            throw ProductNotFoundException::forSlug($slug);
        }

        return $product;
    }

    public function create(ProductData $data, ?User $actor = null): Product
    {
        $product = $this->products->create([
            ...$this->baseAttributes($data),
            'published_at' => $data->status === ProductStatus::Published ? now() : null,
        ]);

        $product = $this->syncCategoriesAndReload($product, $data->categoryIds);

        return $this->applyDefaultVariantValues($product, $data, $actor);
    }

    public function update(Product $product, ProductData $data, ?User $actor = null): Product
    {
        $isNewlyPublished = ! $product->isPublished() && $data->status === ProductStatus::Published;

        $product = $this->products->update($product, [
            ...$this->baseAttributes($data),
            'published_at' => $isNewlyPublished ? now() : $product->published_at,
        ]);

        $product = $this->syncCategoriesAndReload($product, $data->categoryIds);

        return $this->applyDefaultVariantValues($product, $data, $actor);
    }

    /**
     * The admin product form deals in one simplified "quantity" and "price"
     * per product, but those actually live on a ProductVariant's Inventory
     * and Price rows (a product can have many variants). This maps the
     * simplified fields onto the product's default variant — creating one
     * on the fly (auto-generated SKU/name, pack size 1) if it doesn't have
     * one yet — so the form doesn't need to expose the full variant model
     * for the common single-variant case. Multi-variant products keep their
     * other variants untouched; only the default one is affected.
     */
    private function applyDefaultVariantValues(Product $product, ProductData $data, ?User $actor): Product
    {
        if ($data->quantity === null && $data->price === null) {
            return $product;
        }

        $variant = $product->variants->firstWhere('is_default', true) ?? $product->variants->first();

        if ($variant === null) {
            $variant = $this->variants->create($product, new ProductVariantData(
                sku: "P{$product->id}-DEFAULT",
                name: $product->name,
                packSize: 1,
                isDefault: true,
            ));
        }

        if ($data->quantity !== null) {
            $this->inventory->setQuantityOnHand($variant->inventory, $data->quantity);
        }

        if ($data->price !== null) {
            $this->prices->setPrice($variant, new PriceData(Currency::EUR->value, $data->price), changedBy: $actor);
        }

        return $product->refresh()->load(['variants.prices', 'variants.inventory']);
    }

    public function delete(Product $product): void
    {
        $this->products->delete($product);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseAttributes(ProductData $data): array
    {
        return [
            'name' => $data->name,
            'short_description' => $data->shortDescription,
            'description' => $data->description,
            'status' => $data->status,
        ];
    }

    /**
     * @param  list<int>  $categoryIds
     */
    private function syncCategoriesAndReload(Product $product, array $categoryIds): Product
    {
        $product->categories()->sync($categoryIds);

        return $product->refresh()->load('categories');
    }
}
