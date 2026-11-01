<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductBundleRequest;
use App\Http\Requests\Admin\UpdateProductBundleRequest;
use App\Http\Resources\BundleResource;
use App\Models\ProductBundle;
use App\Services\AdminActionLogger;
use App\Services\ProductBundleAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Admin CRUD for kits (1-Person Kit, 2-Person Kit, ...). Lists every kit,
 * active or not - the public GET /v1/bundles only ever shows active ones.
 */
class ProductBundleController extends Controller
{
    public function __construct(
        private readonly ProductBundleAdminService $bundles,
        private readonly AdminActionLogger $actionLogger,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return BundleResource::collection(
            ProductBundle::with(ProductBundle::COMPONENT_EAGER_LOAD)->orderBy('sort_order')->orderBy('id')->get()
        );
    }

    public function show(ProductBundle $bundle): BundleResource
    {
        return new BundleResource($bundle->load(ProductBundle::COMPONENT_EAGER_LOAD));
    }

    public function store(StoreProductBundleRequest $request): JsonResponse
    {
        $bundle = $this->bundles->create($request->validated());

        $this->actionLogger->log($request->user(), 'product_bundle.created', $bundle, ['slug' => $bundle->slug]);

        return (new BundleResource($bundle))->response()->setStatusCode(201);
    }

    public function update(UpdateProductBundleRequest $request, ProductBundle $bundle): BundleResource
    {
        $bundle = $this->bundles->update($bundle, $request->validated());

        $this->actionLogger->log($request->user(), 'product_bundle.updated', $bundle, $request->validated());

        return new BundleResource($bundle);
    }

    public function destroy(Request $request, ProductBundle $bundle): Response
    {
        $this->bundles->delete($bundle);

        $this->actionLogger->log($request->user(), 'product_bundle.deleted', null, ['id' => $bundle->id, 'slug' => $bundle->slug]);

        return response()->noContent();
    }
}
