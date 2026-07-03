<?php

namespace App\Repositories;

use App\DataTransferObjects\ProductFilterData;
use App\Enums\Currency;
use App\Enums\ProductStatus;
use App\Models\Price;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function findBySlug(string $slug, bool $publishedOnly = true): ?Product
    {
        return $this->baseQuery($publishedOnly)
            ->with(['categories.promotions', 'variants.inventory', 'variants.prices', 'media', 'seo', 'promotions'])
            ->where('slug', $slug)
            ->first();
    }

    public function paginate(ProductFilterData $filters, bool $publishedOnly = true, array $with = []): LengthAwarePaginator
    {
        $query = $this->baseQuery($publishedOnly)->with($with);

        $this->applySearch($query, $filters->search);
        $this->applyCategoryFilter($query, $filters->categorySlug);
        $this->applyPriceRange($query, $filters->minPrice, $filters->maxPrice);
        $this->applySort($query, $filters->sort);

        return $query->paginate($filters->perPage, page: $filters->page);
    }

    public function create(array $attributes): Product
    {
        return Product::create($attributes);
    }

    public function update(Product $product, array $attributes): Product
    {
        $product->update($attributes);

        return $product->refresh();
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    /**
     * @return Builder<Product>
     */
    private function baseQuery(bool $publishedOnly): Builder
    {
        $query = Product::query();

        if ($publishedOnly) {
            $query->where('status', ProductStatus::Published);
        }

        return $query;
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applySearch(Builder $query, ?string $search): void
    {
        if ($search === null || $search === '') {
            return;
        }

        $query->where(function (Builder $query) use ($search) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('short_description', 'like', "%{$search}%");
        });
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyCategoryFilter(Builder $query, ?string $categorySlug): void
    {
        if ($categorySlug === null) {
            return;
        }

        $query->whereHas('categories', function (Builder $query) use ($categorySlug) {
            $query->where('slug', $categorySlug);
        });
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyPriceRange(Builder $query, ?float $minPrice, ?float $maxPrice): void
    {
        if ($minPrice === null && $maxPrice === null) {
            return;
        }

        $query->whereHas('variants.prices', function (Builder $query) use ($minPrice, $maxPrice) {
            $query->where('currency', Currency::BGN->value);

            if ($minPrice !== null) {
                $query->where('amount', '>=', $minPrice);
            }

            if ($maxPrice !== null) {
                $query->where('amount', '<=', $maxPrice);
            }
        });
    }

    /**
     * Sorting by price requires a subquery (the minimum variant price per
     * product) since price lives two relationships away from Product —
     * a plain orderBy() can't reach it, and whereHas() only filters.
     *
     * @param  Builder<Product>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $this->orderByPrice($query, 'asc'),
            'price_desc' => $this->orderByPrice($query, 'desc'),
            'name' => $query->orderBy('name'),
            'newest' => $query->orderByDesc('published_at'),
            default => $query->orderByDesc('published_at'),
        };
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function orderByPrice(Builder $query, string $direction): void
    {
        $minPriceSubquery = Price::query()
            ->selectRaw('MIN(prices.amount)')
            ->join('product_variants', 'product_variants.id', '=', 'prices.product_variant_id')
            ->whereColumn('product_variants.product_id', 'products.id')
            ->where('prices.currency', Currency::BGN->value)
            ->whereNull('product_variants.deleted_at');

        $query->orderBy($minPriceSubquery, $direction);
    }
}
