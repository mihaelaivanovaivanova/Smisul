<?php

namespace App\Repositories;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentCategoryRepository implements CategoryRepositoryInterface
{
    public function findBySlug(string $slug, bool $activeOnly = true): ?Category
    {
        $query = Category::query()->where('slug', $slug);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->first();
    }

    public function tree(bool $activeOnly = true): Collection
    {
        $query = Category::query()
            ->whereNull('parent_id')
            ->with(['children' => function ($query) use ($activeOnly) {
                if ($activeOnly) {
                    $query->where('is_active', true);
                }
                $query->orderBy('sort_order');
            }])
            ->orderBy('sort_order');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    public function create(array $attributes): Category
    {
        return Category::create($attributes);
    }

    public function update(Category $category, array $attributes): Category
    {
        $category->update($attributes);

        return $category->refresh();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }
}
