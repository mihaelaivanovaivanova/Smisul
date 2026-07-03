<?php

namespace App\Http\Controllers\Api\V1;

use App\DataTransferObjects\ProductFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductIndexRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Services\CategoryService;
use App\Services\ProductService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categories,
        private readonly ProductService $products,
    ) {}

    public function index(Request $request)
    {
        return CategoryResource::collection($this->categories->tree(activeOnly: true));
    }

    public function show(string $slug): CategoryResource
    {
        $category = $this->categories->findBySlug($slug, activeOnly: true);

        return new CategoryResource($category->load('children'));
    }

    public function products(string $slug, ProductIndexRequest $request)
    {
        // Confirms the category exists (and is active) before listing its
        // products — a 404 here is more useful than an empty result set.
        $this->categories->findBySlug($slug, activeOnly: true);

        $filters = ProductFilterData::fromArray(array_merge($request->validated(), ['category' => $slug]));

        $products = $this->products->list($filters, publishedOnly: true);

        return ProductResource::collection($products);
    }
}
