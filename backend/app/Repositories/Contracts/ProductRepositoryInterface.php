<?php

namespace App\Repositories\Contracts;

use App\DataTransferObjects\ProductFilterData;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    public function findBySlug(string $slug, bool $publishedOnly = true): ?Product;

    /**
     * @param  list<string>  $with
     */
    public function paginate(ProductFilterData $filters, bool $publishedOnly = true, array $with = []): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Product;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Product $product, array $attributes): Product;

    public function delete(Product $product): void;
}
