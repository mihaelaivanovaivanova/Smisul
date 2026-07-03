<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTransferObjects\PromotionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Promotion\StorePromotionRequest;
use App\Http\Requests\Promotion\UpdatePromotionRequest;
use App\Http\Resources\PromotionResource;
use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PromotionController extends Controller
{
    public function __construct(private readonly PromotionService $promotions) {}

    public function index()
    {
        $this->authorize('viewAny', Promotion::class);

        return PromotionResource::collection(Promotion::with(['products', 'categories'])->paginate());
    }

    public function store(StorePromotionRequest $request): JsonResponse
    {
        $promotion = $this->promotions->create(PromotionData::fromArray($request->validated()));

        return (new PromotionResource($promotion))->response()->setStatusCode(201);
    }

    public function show(Promotion $promotion): PromotionResource
    {
        $this->authorize('view', $promotion);

        return new PromotionResource($promotion->load(['products', 'categories']));
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): PromotionResource
    {
        $promotion = $this->promotions->update($promotion, PromotionData::fromArray($request->validated()));

        return new PromotionResource($promotion);
    }

    public function destroy(Promotion $promotion): Response
    {
        $this->authorize('delete', $promotion);

        $this->promotions->delete($promotion);

        return response()->noContent();
    }
}
