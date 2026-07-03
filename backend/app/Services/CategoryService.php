<?php

namespace App\Services;

use App\DataTransferObjects\CategoryData;
use App\Exceptions\CategoryNotFoundException;
use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
    ) {}

    public function tree(bool $activeOnly = true): Collection
    {
        return $this->categories->tree($activeOnly);
    }

    public function findBySlug(string $slug, bool $activeOnly = true): Category
    {
        $category = $this->categories->findBySlug($slug, $activeOnly);

        if ($category === null) {
            throw CategoryNotFoundException::forSlug($slug);
        }

        return $category;
    }

    public function create(CategoryData $data): Category
    {
        return $this->categories->create($this->attributesFrom($data));
    }

    public function update(Category $category, CategoryData $data): Category
    {
        return $this->categories->update($category, $this->attributesFrom($data));
    }

    public function delete(Category $category): void
    {
        $this->categories->delete($category);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFrom(CategoryData $data): array
    {
        return [
            'parent_id' => $data->parentId,
            'name' => $data->name,
            'description' => $data->description,
            'is_active' => $data->isActive,
            'sort_order' => $data->sortOrder,
        ];
    }
}
