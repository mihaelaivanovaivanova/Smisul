<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BundleResource;
use App\Models\ProductBundle;
use App\Services\CartPricingService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public, read-only list of active kits for the landing page's "Complete
 * Kits" view - each with its stored price, the components' live
 * separate-purchase total, and the resolved components themselves.
 */
class BundleController extends Controller
{
    /**
     * A kit whose component was unpublished/deactivated/deleted is left out
     * entirely (it couldn't be added anyway - see CartPricingService::
     * isBundlePurchasable()); one that's merely out of stock is still
     * listed, with is_available false.
     */
    public function index(CartPricingService $pricing): AnonymousResourceCollection
    {
        $bundles = ProductBundle::active()
            ->with([...ProductBundle::COMPONENT_EAGER_LOAD, 'shellProductVariant.product.media'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (ProductBundle $bundle) => $pricing->isBundlePurchasable($bundle))
            ->values();

        return BundleResource::collection($bundles);
    }
}
