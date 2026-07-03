<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTransferObjects\PriceData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\SetPriceRequest;
use App\Http\Resources\PriceResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PriceService;
use Illuminate\Http\JsonResponse;

class PriceController extends Controller
{
    public function __construct(private readonly PriceService $prices) {}

    public function update(SetPriceRequest $request, Product $product, ProductVariant $variant): JsonResponse
    {
        $price = $this->prices->setPrice(
            $variant,
            PriceData::fromArray($request->validated()),
            changedBy: $request->user(),
            reason: $request->validated('reason'),
        );

        // Force 200: this PUT sets "the current price" idempotently. Whether
        // the underlying row was inserted or updated is an implementation
        // detail the client shouldn't see reflected in the status code —
        // JsonResource would otherwise auto-return 201 on first insert.
        return (new PriceResource($price))->response()->setStatusCode(200);
    }
}
