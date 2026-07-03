<?php

namespace App\Services;

use App\DataTransferObjects\ProductData;
use App\DataTransferObjects\ProductFilterData;
use App\Enums\ProductStatus;
use App\Exceptions\ProductNotFoundException;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService
{
    private const DEFAULT_EAGER_LOAD = [
        'categories',
        'variants.prices',
        'variants.inventory',
        'media',
    ];

    public function __construct(
        private readonly ProductRepositoryInterface $products,
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

    public function create(ProductData $data): Product
    {
        $product = $this->products->create([
            ...$this->baseAttributes($data),
            'published_at' => $data->status === ProductStatus::Published ? now() : null,
        ]);

        return $this->syncCategoriesAndReload($product, $data->categoryIds);
    }

    public function update(Product $product, ProductData $data): Product
    {
        $isNewlyPublished = ! $product->isPublished() && $data->status === ProductStatus::Published;

        $product = $this->products->update($product, [
            ...$this->baseAttributes($data),
            'published_at' => $isNewlyPublished ? now() : $product->published_at,
        ]);

        return $this->syncCategoriesAndReload($product, $data->categoryIds);
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
