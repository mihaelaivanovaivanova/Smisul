<?php

namespace App\Repositories\Contracts;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

interface CategoryRepositoryInterface
{
    public function findBySlug(string $slug, bool $activeOnly = true): ?Category;

    /**
     * Root categories (no parent) with their active children eager loaded.
     */
    public function tree(bool $activeOnly = true): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Category;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Category $category, array $attributes): Category;

    public function delete(Category $category): void;
}
