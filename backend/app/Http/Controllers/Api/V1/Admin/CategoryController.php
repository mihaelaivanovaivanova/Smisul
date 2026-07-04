<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTransferObjects\CategoryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\AdminActionLogger;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categories,
        private readonly AdminActionLogger $actionLogger,
    ) {}

    public function index()
    {
        return CategoryResource::collection($this->categories->tree(activeOnly: false));
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categories->create(CategoryData::fromArray($request->validated()));

        $this->actionLogger->log($request->user(), 'category.created', $category);

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category->load('children'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category = $this->categories->update($category, CategoryData::fromArray($request->validated()));

        $this->actionLogger->log($request->user(), 'category.updated', $category);

        return new CategoryResource($category);
    }

    public function destroy(Category $category): Response
    {
        $this->authorize('delete', $category);

        $this->actionLogger->log(request()->user(), 'category.deleted', $category);

        $this->categories->delete($category);

        return response()->noContent();
    }
}
